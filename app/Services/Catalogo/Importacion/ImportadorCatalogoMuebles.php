<?php

namespace App\Services\Catalogo\Importacion;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\Catalogo\ImagenesProducto;
use App\Services\Seo\ImagenOgProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Importa (idempotente) los 25 modelos del catálogo PDF de muebles 2026.
 *
 * Sin opciones quedan PENDIENTES DE APROBACIÓN, sin precio de venta, sin existencias y sin imágenes.
 * Opciones (decisión del dueño, docs/ARQUITECTURA.md §9):
 * - `imagenes`: adjunta las fotos extraídas del PDF (database/data/catalogo_muebles_mx_2026/imagenes,
 *   manifiesto `imagenes.json`) SÓLO si el producto aún no tiene imágenes (no duplica).
 * - `publicar`: precio de venta = precio del catálogo SÓLO si no tiene precio; `bajo_pedido` si sigue
 *   en inventario con 0 existencias y sin movimientos; publica (visible, activo, aprobado) los que aún
 *   no fueron revisados (borrador/pendiente) y ya no tienen faltantes. Publicados/pausados no se tocan.
 * - `forzarPrecios`: re-sincroniza el precio de venta con el del catálogo aunque ya exista.
 *
 * Reglas comunes:
 * - Llave idempotente: `importacion_clave` (respaldo: SKU interno `ML-MUE-<codigo>`).
 * - Reimportar sólo llena campos vacíos y actualiza los datos de referencia
 *   (`precio_referencia`, `referencia_consultada_en`). Nunca toca nombre, slug ni existencias, ni
 *   (fuera de las opciones anteriores) precio, estado, visibilidad o imágenes del administrador.
 * - No se asigna marca comercial.
 */
class ImportadorCatalogoMuebles
{
    public const ORIGEN = 'catalogo_muebles_mx_2026';

    public const PREFIJO_SKU = 'ML-MUE-';

    public const CATEGORIA_PADRE = 'Muebles';

    /** Categorías del catálogo en el orden del PDF. */
    public const CATEGORIAS = ['Sala', 'Comedor', 'Recámara', 'Home Office', 'Organización', 'Terraza'];

    /** Campos que se llenan sólo si están vacíos (respetan ediciones del admin). */
    private const CAMPOS_SI_VACIOS = [
        'categoria_id', 'codigo_referencia', 'modelo', 'acabado', 'dimensiones', 'observaciones',
        'descripcion', 'atributos', 'fuente_referencia', 'importacion_origen', 'importacion_clave',
    ];

    /** Datos de referencia que siempre se sincronizan con la fuente. */
    private const CAMPOS_REFERENCIA = ['precio_referencia', 'referencia_consultada_en'];

    /** @var array<string, Categoria|null> */
    private array $categorias = [];

    /** Estados en los que el importador aún puede decidir modalidad y publicación. */
    private const ESTADOS_SIN_REVISAR = [EstadoPublicacion::Borrador->value, EstadoPublicacion::PendienteAprobacion->value];

    public function __construct(private readonly ImagenesProducto $imagenesProducto) {}

    public static function rutaDatos(): string
    {
        return database_path('data/catalogo_muebles_mx_2026.json');
    }

    /** Carpeta con las fotos extraídas del PDF (`imagenes/`) y su manifiesto `imagenes.json`. */
    public static function rutaImagenes(): string
    {
        return database_path('data/catalogo_muebles_mx_2026');
    }

    public function importar(
        bool $simulacion = false,
        ?string $ruta = null,
        bool $imagenes = false,
        bool $publicar = false,
        bool $forzarPrecios = false,
    ): ResultadoImportacion {
        $datos = $this->leer($ruta ?? self::rutaDatos());
        $resultado = new ResultadoImportacion($simulacion);
        $this->categorias = [];
        /** @var list<array{0: Producto, 1: array<string, mixed>}> $importados */
        $importados = [];

        $ejecutar = function () use ($datos, $simulacion, $resultado, &$importados) {
            $padre = $this->categoria(self::CATEGORIA_PADRE, null, 0, $simulacion, $resultado);

            foreach (self::CATEGORIAS as $orden => $nombre) {
                $this->categoria($nombre, $padre, $orden + 1, $simulacion, $resultado);
            }

            foreach ($datos['productos'] as $item) {
                $importados[] = [$this->importarProducto($item, $datos, $simulacion, $resultado), $item];
            }
        };

        // En simulación no se escribe nada (no dependemos de rollback: MyISAM no lo soporta).
        $simulacion ? $ejecutar() : DB::transaction($ejecutar);

        if ($imagenes || $publicar || $forzarPrecios) {
            $manifiesto = $imagenes ? $this->manifiestoImagenes() : [];

            // Fuera de la transacción: escribe archivos en disco (un fallo no deshace la importación base).
            foreach ($importados as [$producto, $item]) {
                $this->prepararVenta($producto, $item, $manifiesto, $simulacion, $resultado, $imagenes, $publicar, $forzarPrecios);
            }
        }

        return $resultado;
    }

    /**
     * Segunda fase (opcional): precio de venta, modalidad, fotos y publicación.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, list<string>>  $manifiesto
     */
    private function prepararVenta(
        Producto $producto,
        array $item,
        array $manifiesto,
        bool $simulacion,
        ResultadoImportacion $resultado,
        bool $imagenes,
        bool $publicar,
        bool $forzarPrecios,
    ): void {
        $cambios = [];
        $sinRevisar = in_array($producto->estado_publicacion, self::ESTADOS_SIN_REVISAR, true);
        $precioCatalogo = (float) ($item['precio_referencia'] ?? 0) > 0
            ? number_format((float) $item['precio_referencia'], 2, '.', '')
            : null;

        // 1) Precio de venta = precio del catálogo (sólo si no tiene; con --forzar-precios, siempre).
        $precioCambio = false;

        if ($precioCatalogo !== null && (($publicar && $producto->precio === null)
            || ($forzarPrecios && (float) $producto->precio !== (float) $precioCatalogo))) {
            $producto->precio = $precioCatalogo;
            $cambios[] = 'precio $'.number_format((float) $precioCatalogo, 2);
            $precioCambio = true;
        }

        // 2) Sin existencias y sin decisión del admin → bajo pedido (no se inventan existencias).
        if ($publicar && $sinRevisar
            && $producto->modalidad_venta === ModalidadVenta::Inventario->value
            && (int) $producto->stock === 0
            && (! $producto->exists || ! $producto->movimientosInventario()->exists())) {
            $producto->modalidad_venta = ModalidadVenta::BajoPedido->value;
            $cambios[] = 'bajo pedido';
        }

        if (! $simulacion && $producto->isDirty()) {
            $producto->save();
        }

        // 3) Fotos del catálogo, sólo si el producto aún no tiene ninguna (re-ejecutar no duplica).
        $conImagenes = filled($producto->imagen_principal) || ($producto->exists && $producto->imagenes()->exists());
        $avisos = [];

        if ($imagenes && ! $conImagenes) {
            $archivos = $manifiesto[(string) $producto->codigo_referencia] ?? [];

            if ($archivos === []) {
                $avisos[] = 'No hay fotos del catálogo para este modelo.';
            } elseif ($simulacion) {
                $producto->imagen_principal = basename($archivos[0]); // sólo en memoria, para evaluar la publicación
                $cambios[] = count($archivos).' imagen(es)';
            } else {
                $this->imagenesProducto->agregar($producto, array_map(
                    fn (string $ruta) => new UploadedFile($ruta, basename($ruta), null, null, true),
                    $archivos,
                ));
                $producto->refresh();
                $cambios[] = count($archivos).' imagen(es)';
                $precioCambio = false; // agregar() ya generó la imagen OG con el precio actual
            }
        }

        // 4) Publicar si ya no le falta nada (no toca publicados ni pausados por el admin).
        $publicado = false;

        if ($publicar && $sinRevisar) {
            $faltantes = $producto->faltantesParaPublicar();

            if ($faltantes === []) {
                $producto->forceFill([
                    'visible' => true,
                    'activo' => true,
                    'estado_publicacion' => EstadoPublicacion::Publicado->value,
                    'aprobado_en' => now(),
                    'publicado_en' => $producto->publicado_en ?? now(),
                ]);

                if (! $simulacion) {
                    $producto->save();
                }

                $cambios[] = 'publicado';
                $publicado = true;
            } else {
                array_push($avisos, ...$faltantes);
            }
        } elseif ($publicar && $producto->estado_publicacion === EstadoPublicacion::Pausado->value) {
            $avisos[] = 'Pausado por el administrador: no se publica automáticamente.';
        }

        // La imagen para compartir muestra el precio: regenerarla si cambió y ya tenía fotos.
        if (! $simulacion && $precioCambio && $conImagenes) {
            app(ImagenOgProducto::class)->regenerar($producto);
        }

        $resultado->registrarVenta($producto->sku, $producto->nombre, $cambios, $avisos, $publicado);
    }

    /**
     * Rutas absolutas de las fotos por código de referencia (manifiesto `imagenes.json`).
     *
     * @return array<string, list<string>>
     */
    private function manifiestoImagenes(): array
    {
        $base = self::rutaImagenes();
        $archivo = $base.DIRECTORY_SEPARATOR.'imagenes.json';

        if (! is_file($archivo)) {
            throw new InvalidArgumentException("No se encontró el manifiesto de imágenes del catálogo: {$archivo}");
        }

        $datos = json_decode((string) file_get_contents($archivo), true);
        $rutas = [];

        foreach ((array) ($datos['imagenes'] ?? []) as $codigo => $nombres) {
            foreach ((array) $nombres as $nombre) {
                $ruta = $base.DIRECTORY_SEPARATOR.'imagenes'.DIRECTORY_SEPARATOR.basename((string) $nombre);

                if (is_file($ruta)) {
                    $rutas[(string) $codigo][] = $ruta;
                }
            }
        }

        return $rutas;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $datos
     */
    private function importarProducto(array $item, array $datos, bool $simulacion, ResultadoImportacion $resultado): Producto
    {
        $codigo = trim((string) $item['codigo_referencia']);
        $clave = trim((string) ($item['importacion_clave'] ?? '')) ?: self::ORIGEN.':'.$codigo;
        $sku = self::PREFIJO_SKU.Str::upper($codigo);
        $categoria = $this->categorias[Str::slug((string) $item['categoria'])] ?? null;

        $fuente = trim((string) ($datos['fuente_referencia'] ?? ''));
        $url = trim((string) ($item['url_referencia'] ?? ''));

        $valores = [
            'categoria_id' => $categoria?->id,
            'codigo_referencia' => $codigo,
            'modelo' => $this->texto($item['modelo'] ?? null),
            'acabado' => $this->texto($item['acabado'] ?? null),
            'dimensiones' => $this->texto($item['dimensiones'] ?? null),
            'observaciones' => $this->texto($item['observaciones'] ?? null),
            'descripcion' => $this->descripcion($item),
            'atributos' => filled($item['tipo'] ?? null) ? [['nombre' => 'Tipo', 'valor' => trim((string) $item['tipo'])]] : null,
            // La URL va junto a la fuente: es un dato interno (columna oculta al serializar).
            'fuente_referencia' => Str::limit(trim($fuente.($url !== '' ? ' | '.$url : ''), ' |'), 255, ''),
            'importacion_origen' => (string) ($datos['origen'] ?? self::ORIGEN),
            'importacion_clave' => $clave,
            'precio_referencia' => isset($item['precio_referencia']) ? number_format((float) $item['precio_referencia'], 2, '.', '') : null,
            'referencia_consultada_en' => $datos['referencia_consultada_en'] ?? null,
        ];

        $producto = Producto::query()->where('importacion_clave', $clave)->first()
            ?? Producto::query()->where('sku', $sku)->first();

        if (! $producto) {
            $producto = new Producto;
            $producto->forceFill([
                ...$valores,
                'nombre' => Str::limit(trim((string) $item['nombre']), 180, ''),
                'sku' => $sku,
                'slug' => $this->slugUnico((string) $item['nombre'], $codigo),
                'marca_id' => null,
                'precio' => null,
                'precio_comparacion' => null,
                'costo' => null,
                'stock' => 0,
                'stock_minimo' => 0,
                'imagen_principal' => null,
                'destacado' => false,
                'activo' => true,
                'visible' => false,
                'estado_publicacion' => EstadoPublicacion::PendienteAprobacion->value,
                'modalidad_venta' => ModalidadVenta::Inventario->value,
                'publicado_en' => null,
            ]);

            if (! $simulacion) {
                $producto->save();
            }

            $resultado->registrar($clave, $sku, $producto->nombre, ResultadoImportacion::CREADO);

            return $producto;
        }

        foreach (self::CAMPOS_SI_VACIOS as $campo) {
            $actual = $producto->getAttribute($campo);

            if (($actual === null || $actual === '' || $actual === []) && $valores[$campo] !== null) {
                $producto->setAttribute($campo, $valores[$campo]);
            }
        }

        foreach (self::CAMPOS_REFERENCIA as $campo) {
            if ($valores[$campo] !== null) {
                $producto->setAttribute($campo, $valores[$campo]);
            }
        }

        $cambios = array_keys($producto->getDirty());

        if ($cambios === []) {
            $resultado->registrar($clave, $producto->sku, $producto->nombre, ResultadoImportacion::SIN_CAMBIOS);

            return $producto;
        }

        if (! $simulacion) {
            $producto->save();
        }

        $resultado->registrar($clave, $producto->sku, $producto->nombre, ResultadoImportacion::ACTUALIZADO, $cambios);

        return $producto;
    }

    private function categoria(string $nombre, ?Categoria $padre, int $orden, bool $simulacion, ResultadoImportacion $resultado): ?Categoria
    {
        $slug = Str::slug($nombre);

        if (array_key_exists($slug, $this->categorias)) {
            return $this->categorias[$slug];
        }

        $categoria = Categoria::query()->where('slug', $slug)->first();

        if (! $categoria) {
            $resultado->categoriasCreadas[] = $nombre;

            if (! $simulacion) {
                $categoria = Categoria::create([
                    'categoria_padre_id' => $padre?->id,
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'activa' => true,
                    'orden' => $orden,
                ]);
            }
        }

        return $this->categorias[$slug] = $categoria;
    }

    /**
     * Slug estable: el nombre; si ya existe otro producto con ese slug, nombre + código.
     */
    private function slugUnico(string $nombre, string $codigo): string
    {
        $base = Str::limit(Str::slug($nombre) ?: 'mueble', 180, '');
        $candidato = $base;
        $intento = 1;

        while (Producto::query()->where('slug', $candidato)->exists()) {
            $candidato = $intento === 1
                ? $base.'-'.Str::lower($codigo)
                : $base.'-'.Str::lower($codigo).'-'.$intento;
            $intento++;
        }

        return $candidato;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function descripcion(array $item): ?string
    {
        $titular = rtrim((string) $this->texto($item['titular'] ?? null), '.');
        $texto = $this->texto($item['descripcion'] ?? null);

        $partes = array_filter([$titular !== '' ? $titular.'.' : null, $texto]);

        return $partes === [] ? null : implode(' ', $partes);
    }

    private function texto(mixed $valor): ?string
    {
        $valor = trim(strip_tags((string) $valor));

        return $valor === '' ? null : $valor;
    }

    /**
     * @return array<string, mixed>
     */
    private function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new InvalidArgumentException("No se encontró el archivo de datos del catálogo: {$ruta}");
        }

        $datos = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($datos) || ! is_array($datos['productos'] ?? null)) {
            throw new InvalidArgumentException('El archivo de datos del catálogo no tiene el formato esperado.');
        }

        foreach ($datos['productos'] as $i => $item) {
            foreach (['categoria', 'nombre', 'codigo_referencia'] as $campo) {
                if (blank($item[$campo] ?? null)) {
                    throw new InvalidArgumentException('El modelo #'.($i + 1)." no tiene «{$campo}».");
                }
            }
        }

        return $datos;
    }
}
