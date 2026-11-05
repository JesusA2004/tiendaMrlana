<?php

namespace App\Services\Catalogo\Importacion;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Importa (idempotente) los 25 modelos del catálogo PDF de muebles 2026 como productos
 * PENDIENTES DE APROBACIÓN, sin precio de venta, sin existencias y sin imágenes.
 *
 * Reglas (docs/ARQUITECTURA.md §9):
 * - Llave idempotente: `importacion_clave` (respaldo: SKU interno `ML-MUE-<codigo>`).
 * - Reimportar sólo llena campos vacíos y actualiza los datos de referencia
 *   (`precio_referencia`, `referencia_consultada_en`). Nunca toca precio, existencias,
 *   estado, visibilidad, nombre, slug ni imágenes que el administrador ya definió.
 * - Los precios del PDF son referencias públicas de otra tienda, NO precios de venta.
 * - No se asigna marca comercial ni se extraen fotografías.
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

    public static function rutaDatos(): string
    {
        return database_path('data/catalogo_muebles_mx_2026.json');
    }

    public function importar(bool $simulacion = false, ?string $ruta = null): ResultadoImportacion
    {
        $datos = $this->leer($ruta ?? self::rutaDatos());
        $resultado = new ResultadoImportacion($simulacion);
        $this->categorias = [];

        $ejecutar = function () use ($datos, $simulacion, $resultado) {
            $padre = $this->categoria(self::CATEGORIA_PADRE, null, 0, $simulacion, $resultado);

            foreach (self::CATEGORIAS as $orden => $nombre) {
                $this->categoria($nombre, $padre, $orden + 1, $simulacion, $resultado);
            }

            foreach ($datos['productos'] as $item) {
                $this->importarProducto($item, $datos, $simulacion, $resultado);
            }
        };

        // En simulación no se escribe nada (no dependemos de rollback: MyISAM no lo soporta).
        $simulacion ? $ejecutar() : DB::transaction($ejecutar);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $datos
     */
    private function importarProducto(array $item, array $datos, bool $simulacion, ResultadoImportacion $resultado): void
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

            return;
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

            return;
        }

        if (! $simulacion) {
            $producto->save();
        }

        $resultado->registrar($clave, $producto->sku, $producto->nombre, ResultadoImportacion::ACTUALIZADO, $cambios);
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
