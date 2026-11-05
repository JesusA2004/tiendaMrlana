<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Enums\TipoMovimientoInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogo\ProductoRapidoRequest;
use App\Http\Requests\Admin\Catalogo\ProductoRequest;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\PedidoItem;
use App\Models\Producto;
use App\Services\Catalogo\ImagenesProducto;
use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use App\Services\Inventario\InventarioService;
use App\Support\ImagenUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo administrativo de productos. Las existencias sólo cambian vía InventarioService
 * y la publicación vía ProductoPublicacionController.
 */
class ProductoController extends Controller
{
    public const POR_PAGINA = 15;

    /** Requisitos de publicación evaluados para el indicador de completitud. */
    public const TOTAL_REQUISITOS = 4;

    public function __construct(
        private readonly InventarioService $inventario,
        private readonly ImagenesProducto $imagenes,
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(EstadoPublicacion::values())],
            'categoria_id' => ['nullable', 'integer'],
            'marca_id' => ['nullable', 'integer'],
            'modalidad' => ['nullable', Rule::in(ModalidadVenta::values())],
            'stock' => ['nullable', Rule::in(['agotado', 'bajo_minimo'])],
            'orden' => ['nullable', Rule::in(['recientes', 'nombre', 'precio', 'stock'])],
        ]);

        $consulta = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'imagenes:id,producto_id,ruta,orden,principal'])
            ->select('productos.*')
            ->selectSub(PedidoItem::query()->selectRaw('COUNT(*)')->whereColumn('pedido_items.producto_id', 'productos.id'), 'pedidos_count');

        $this->filtrar($consulta, $filtros, conEstado: true);

        match ($filtros['orden'] ?? 'recientes') {
            'nombre' => $consulta->orderBy('nombre'),
            'precio' => $consulta->orderByRaw('precio IS NULL')->orderBy('precio'),
            'stock' => $consulta->orderBy('stock'),
            default => $consulta->latest('id'),
        };

        $productos = $consulta
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Producto $p) => $this->fila($p));

        // Conteo por estado respetando los demás filtros (para las pestañas).
        $conteoBase = Producto::query();
        $this->filtrar($conteoBase, $filtros, conEstado: false);
        $conteos = $conteoBase->select('estado_publicacion', DB::raw('COUNT(*) as total'))
            ->groupBy('estado_publicacion')
            ->pluck('total', 'estado_publicacion');

        return Inertia::render('Admin/Productos/Index', [
            'productos' => $productos,
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'estado' => $filtros['estado'] ?? '',
                'categoria_id' => isset($filtros['categoria_id']) ? (int) $filtros['categoria_id'] : null,
                'marca_id' => isset($filtros['marca_id']) ? (int) $filtros['marca_id'] : null,
                'modalidad' => $filtros['modalidad'] ?? '',
                'stock' => $filtros['stock'] ?? '',
                'orden' => $filtros['orden'] ?? 'recientes',
            ],
            'conteos' => collect(EstadoPublicacion::values())
                ->mapWithKeys(fn (string $e) => [$e => (int) ($conteos[$e] ?? 0)])
                ->put('todos', (int) $conteos->sum()),
            'resumen' => [
                'agotados' => Producto::query()->where('modalidad_venta', ModalidadVenta::Inventario->value)
                    ->where('stock', '<=', 0)->where('estado_publicacion', EstadoPublicacion::Publicado->value)->count(),
                'bajo_minimo' => $this->bajoMinimo(Producto::query())->count(),
                'importados_pendientes' => Producto::query()->where('importacion_origen', ImportadorCatalogoMuebles::ORIGEN)
                    ->where('estado_publicacion', EstadoPublicacion::PendienteAprobacion->value)->count(),
            ],
            ...$this->catalogos(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Productos/Form', [
            'producto' => null,
            ...$this->catalogos(),
            ...$this->opcionesFormulario(),
        ]);
    }

    public function store(ProductoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $producto = DB::transaction(function () use ($datos, $request) {
            $producto = new Producto;
            $producto->forceFill([
                ...$this->atributosDesde($datos),
                'sku' => $datos['sku'] ?? $this->skuNuevo(),
                'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre']),
                'stock' => 0,
                'estado_publicacion' => EstadoPublicacion::Borrador->value,
                'activo' => true,
                'visible' => false,
            ])->save();

            $inicial = (int) ($datos['stock_inicial'] ?? 0);

            if ($inicial > 0) {
                $this->inventario->entrada($producto, $inicial, $request->user(), 'Existencia inicial al dar de alta');
            }

            return $producto;
        });

        return redirect()
            ->route('admin.productos.edit', $producto)
            ->with('success', 'Producto creado como borrador. Agrega imágenes y envíalo a aprobación cuando esté listo.');
    }

    public function show(Producto $producto): RedirectResponse
    {
        return redirect()->route('admin.productos.edit', $producto);
    }

    public function edit(Producto $producto): Response
    {
        $producto->load(['categoria:id,nombre', 'marca:id,nombre', 'imagenes', 'aprobadoPor:id,name']);

        $movimientos = $producto->movimientosInventario()
            ->with('user:id,name')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'tipo' => TipoMovimientoInventario::tryFrom($m->tipo)?->toArray() ?? ['value' => $m->tipo, 'label' => $m->tipo, 'color' => 'neutral'],
                'cantidad' => $m->cantidad,
                'stock_anterior' => $m->stock_anterior,
                'stock_nuevo' => $m->stock_nuevo,
                'motivo' => $m->motivo,
                'usuario' => $m->user?->name,
                'fecha' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Productos/Form', [
            'producto' => $this->detalle($producto),
            'movimientos' => $movimientos,
            ...$this->catalogos(),
            ...$this->opcionesFormulario(),
        ]);
    }

    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validated();

        $producto->forceFill([
            ...$this->atributosDesde($datos),
            'sku' => $datos['sku'] ?? $producto->sku,
            'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre'], $producto->id, $producto->slug),
        ])->save();

        return back()->with('success', 'Cambios guardados.');
    }

    /**
     * Edición rápida (cola de aprobación): precio, categoría y modalidad.
     */
    public function rapido(ProductoRapidoRequest $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validated();

        if ($producto->precio_comparacion !== null && $datos['precio'] !== null
            && (float) $producto->precio_comparacion <= (float) $datos['precio']) {
            return back()->withErrors(['precio' => 'El precio debe ser menor que el precio de comparación ('.$producto->precio_comparacion.').']);
        }

        $producto->forceFill([
            'precio' => $datos['precio'],
            'categoria_id' => $datos['categoria_id'] ?? null,
            'modalidad_venta' => $datos['modalidad_venta'],
            'tiempo_entrega_dias' => $datos['modalidad_venta'] === ModalidadVenta::BajoPedido->value
                ? $datos['tiempo_entrega_dias']
                : null,
        ])->save();

        return back()->with('success', 'Datos de «'.$producto->nombre.'» actualizados.');
    }

    /**
     * Sólo se elimina si nunca se ha vendido; si tiene pedidos, se pausa para conservar el historial.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        if (self::tienePedidos($producto)) {
            $producto->forceFill(['estado_publicacion' => EstadoPublicacion::Pausado->value])->save();

            return back()->with('success', 'El producto tiene pedidos, así que se pausó en lugar de eliminarse.');
        }

        DB::transaction(function () use ($producto) {
            $this->imagenes->eliminarTodas($producto);
            $producto->delete();
        });

        return redirect()->route('admin.productos.index')->with('success', 'Producto eliminado.');
    }

    // ------------------------------------------------------------------ Helpers

    /**
     * @param  Builder<Producto>  $consulta
     * @param  array<string, mixed>  $filtros
     */
    private function filtrar(Builder $consulta, array $filtros, bool $conEstado): void
    {
        $q = trim((string) ($filtros['q'] ?? ''));

        $consulta
            ->when($q !== '', function (Builder $query) use ($q) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $query->where(fn (Builder $w) => $w
                    ->where('nombre', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('modelo', 'like', $like)
                    ->orWhere('codigo_referencia', 'like', $like));
            })
            ->when($conEstado && filled($filtros['estado'] ?? null), fn (Builder $query) => $query->where('estado_publicacion', $filtros['estado']))
            ->when(filled($filtros['categoria_id'] ?? null), fn (Builder $query) => $query->where('categoria_id', $filtros['categoria_id']))
            ->when(filled($filtros['marca_id'] ?? null), fn (Builder $query) => $query->where('marca_id', $filtros['marca_id']))
            ->when(filled($filtros['modalidad'] ?? null), fn (Builder $query) => $query->where('modalidad_venta', $filtros['modalidad']))
            ->when(($filtros['stock'] ?? null) === 'agotado', fn (Builder $query) => $query
                ->where('modalidad_venta', ModalidadVenta::Inventario->value)
                ->where('stock', '<=', 0))
            ->when(($filtros['stock'] ?? null) === 'bajo_minimo', fn (Builder $query) => $this->bajoMinimo($query));
    }

    public static function tienePedidos(Producto $producto): bool
    {
        return PedidoItem::query()->where('producto_id', $producto->id)->exists();
    }

    /**
     * Productos de inventario con existencia mínima definida y stock en o por debajo de ella.
     *
     * @param  Builder<Producto>  $consulta
     * @return Builder<Producto>
     */
    public static function bajoMinimo(Builder $consulta): Builder
    {
        return $consulta
            ->where('modalidad_venta', ModalidadVenta::Inventario->value)
            ->where('stock_minimo', '>', 0)
            ->whereColumn('stock', '<=', 'stock_minimo');
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(Producto $producto): array
    {
        $faltantes = $producto->faltantesParaPublicar();

        return [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'sku' => $producto->sku,
            'slug' => $producto->slug,
            'modelo' => $producto->modelo,
            'codigo_referencia' => $producto->codigo_referencia,
            'acabado' => $producto->acabado,
            'precio' => $producto->precio !== null ? (float) $producto->precio : null,
            'precio_referencia' => $producto->precio_referencia !== null ? (float) $producto->precio_referencia : null,
            'stock' => (int) $producto->stock,
            'stock_minimo' => (int) $producto->stock_minimo,
            'categoria_id' => $producto->categoria_id,
            'categoria' => $producto->categoria?->nombre,
            'marca' => $producto->marca?->nombre,
            'modalidad_venta' => $producto->modalidad_venta,
            'tiempo_entrega_dias' => $producto->tiempo_entrega_dias,
            'estado' => EstadoPublicacion::tryFrom((string) $producto->estado_publicacion)?->toArray()
                ?? ['value' => $producto->estado_publicacion, 'label' => $producto->estado_publicacion, 'color' => 'neutral'],
            'destacado' => (bool) $producto->destacado,
            'imagen' => $producto->imagenPrincipalUrl(),
            'imagenes_count' => $producto->imagenes->count(),
            'faltantes' => $faltantes,
            'completitud' => $this->completitud($faltantes),
            'importado' => $producto->importacion_origen !== null,
            'tiene_pedidos' => $producto->getAttribute('pedidos_count') !== null
                ? (int) $producto->getAttribute('pedidos_count') > 0
                : self::tienePedidos($producto),
            'url_publica' => $producto->estaPublicado() ? route('tienda.show', $producto->slug) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(Producto $producto): array
    {
        $fuente = (string) $producto->fuente_referencia;
        $url = null;

        if (preg_match('~https?://\S+~', $fuente, $m)) {
            $url = $m[0];
            $fuente = trim(str_replace($m[0], '', $fuente), " |\t");
        }

        $faltantes = $producto->faltantesParaPublicar();

        return [
            ...$this->fila($producto),
            'marca_id' => $producto->marca_id,
            'descripcion' => $producto->descripcion,
            'descripcion_larga' => $producto->descripcion_larga,
            'precio_comparacion' => $producto->precio_comparacion !== null ? (float) $producto->precio_comparacion : null,
            'costo' => $producto->costo !== null ? (float) $producto->costo : null,
            'peso' => $producto->peso !== null ? (float) $producto->peso : null,
            'alto' => $producto->alto !== null ? (float) $producto->alto : null,
            'ancho' => $producto->ancho !== null ? (float) $producto->ancho : null,
            'largo' => $producto->largo !== null ? (float) $producto->largo : null,
            'dimensiones' => $producto->dimensiones,
            'observaciones' => $producto->observaciones,
            'atributos' => array_values(array_filter((array) ($producto->atributos ?? []), 'is_array')),
            'meta_titulo' => $producto->meta_titulo,
            'meta_descripcion' => $producto->meta_descripcion,
            'puede_publicarse' => $faltantes === [],
            'aprobado_por' => $producto->aprobadoPor?->name,
            'aprobado_en' => $producto->aprobado_en?->toIso8601String(),
            'publicado_en' => $producto->publicado_en?->toIso8601String(),
            'imagen_heredada' => $producto->imagenes->isEmpty() ? ImagenUrl::resolver($producto->imagen_principal) : null,
            'imagenes' => $producto->imagenes
                ->sortBy([['orden', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => ImagenUrl::resolver($img->ruta),
                    'principal' => (bool) $img->principal,
                    'orden' => (int) $img->orden,
                ]),
            'referencia' => [
                'precio' => $producto->precio_referencia !== null ? (float) $producto->precio_referencia : null,
                'fuente' => $fuente !== '' ? $fuente : null,
                'url' => $url,
                'consultada_en' => $producto->referencia_consultada_en?->toDateString(),
                'codigo' => $producto->codigo_referencia,
                'origen' => $producto->importacion_origen,
            ],
        ];
    }

    /**
     * @param  list<string>  $faltantes
     */
    private function completitud(array $faltantes): int
    {
        $pendientes = min(self::TOTAL_REQUISITOS, count($faltantes));

        return (int) round((self::TOTAL_REQUISITOS - $pendientes) / self::TOTAL_REQUISITOS * 100);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function atributosDesde(array $datos): array
    {
        $bajoPedido = $datos['modalidad_venta'] === ModalidadVenta::BajoPedido->value;

        return [
            'nombre' => $datos['nombre'],
            'categoria_id' => $datos['categoria_id'] ?? null,
            'marca_id' => $datos['marca_id'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'descripcion_larga' => $datos['descripcion_larga'] ?? null,
            'precio' => $datos['precio'] ?? null,
            'precio_comparacion' => $datos['precio_comparacion'] ?? null,
            'costo' => $datos['costo'] ?? null,
            'modalidad_venta' => $datos['modalidad_venta'],
            'tiempo_entrega_dias' => $bajoPedido ? ($datos['tiempo_entrega_dias'] ?? null) : null,
            'stock_minimo' => (int) ($datos['stock_minimo'] ?? 0),
            'peso' => $datos['peso'] ?? null,
            'alto' => $datos['alto'] ?? null,
            'ancho' => $datos['ancho'] ?? null,
            'largo' => $datos['largo'] ?? null,
            'modelo' => $datos['modelo'] ?? null,
            'acabado' => $datos['acabado'] ?? null,
            'dimensiones' => $datos['dimensiones'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'atributos' => ! empty($datos['atributos']) ? array_values($datos['atributos']) : null,
            'meta_titulo' => $datos['meta_titulo'] ?? null,
            'meta_descripcion' => $datos['meta_descripcion'] ?? null,
            'destacado' => (bool) ($datos['destacado'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'categorias' => Categoria::query()
                ->with('padre:id,nombre')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'categoria_padre_id', 'activa'])
                ->map(fn (Categoria $c) => [
                    'id' => $c->id,
                    'nombre' => $c->padre ? $c->padre->nombre.' › '.$c->nombre : $c->nombre,
                    'activa' => (bool) $c->activa,
                ])
                ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'marcas' => Marca::query()->orderBy('nombre')->get(['id', 'nombre', 'activa']),
            'estados' => EstadoPublicacion::options(),
            'modalidades' => ModalidadVenta::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opcionesFormulario(): array
    {
        return [
            'limites' => [
                'imagen_max_mb' => 5,
                'imagenes_por_subida' => 10,
            ],
        ];
    }

    private function skuNuevo(): string
    {
        do {
            $sku = 'ML-'.Str::upper(Str::random(8));
        } while (Producto::query()->where('sku', $sku)->exists());

        return $sku;
    }

    private function slugUnico(?string $slug, string $nombre, ?int $ignorar = null, ?string $actual = null): string
    {
        // Si no se especifica, se conserva el slug actual (URLs estables).
        if (! $slug && $actual) {
            return $actual;
        }

        $base = Str::limit(Str::slug($slug ?: $nombre) ?: 'producto', 190, '');
        $candidato = $base;
        $n = 2;

        while (Producto::query()
            ->when($ignorar, fn (Builder $q) => $q->whereKeyNot($ignorar))
            ->where('slug', $candidato)
            ->exists()) {
            $candidato = $base.'-'.$n++;
        }

        return $candidato;
    }
}
