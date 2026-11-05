<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Enums\TipoMovimientoInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogo\AjusteInventarioRequest;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Services\Inventario\InventarioService;
use App\Support\ImagenUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bitácora de inventario y ajustes manuales. Todo cambio de existencias pasa por InventarioService.
 */
class InventarioController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'producto_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', Rule::in(TipoMovimientoInventario::values())],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $zona = 'America/Mexico_City';

        $movimientos = InventarioMovimiento::query()
            ->with(['producto:id,nombre,sku,slug', 'user:id,name', 'pedido:id,folio'])
            ->when($filtros['producto_id'] ?? null, fn (Builder $q, $id) => $q->where('producto_id', $id))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $desde) => $q->where('created_at', '>=', Carbon::parse($desde, $zona)->startOfDay()->utc()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $hasta) => $q->where('created_at', '<=', Carbon::parse($hasta, $zona)->endOfDay()->utc()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventarioMovimiento $m) => [
                'id' => $m->id,
                'producto' => $m->producto ? ['id' => $m->producto->id, 'nombre' => $m->producto->nombre, 'sku' => $m->producto->sku] : null,
                'tipo' => TipoMovimientoInventario::tryFrom($m->tipo)?->toArray() ?? ['value' => $m->tipo, 'label' => $m->tipo, 'color' => 'neutral'],
                'cantidad' => $m->cantidad,
                'stock_anterior' => $m->stock_anterior,
                'stock_nuevo' => $m->stock_nuevo,
                'motivo' => $m->motivo,
                'usuario' => $m->user?->name,
                'pedido' => $m->pedido ? ['id' => $m->pedido->id, 'folio' => $m->pedido->folio] : null,
                'fecha' => $m->created_at?->toIso8601String(),
            ]);

        $bajoMinimo = ProductoController::bajoMinimo(Producto::query()->with('imagenes:id,producto_id,ruta,orden,principal'))
            ->orderByRaw('stock - stock_minimo')
            ->limit(12)
            ->get()
            ->map(fn (Producto $p) => $this->resumenProducto($p));

        $agotados = Producto::query()
            ->with('imagenes:id,producto_id,ruta,orden,principal')
            ->where('modalidad_venta', ModalidadVenta::Inventario->value)
            ->where('stock', '<=', 0)
            ->where('estado_publicacion', EstadoPublicacion::Publicado->value)
            ->orderBy('nombre')
            ->limit(12)
            ->get()
            ->map(fn (Producto $p) => $this->resumenProducto($p));

        $inventario = Producto::query()->where('modalidad_venta', ModalidadVenta::Inventario->value);

        return Inertia::render('Admin/Inventario/Index', [
            'movimientos' => $movimientos,
            'filtros' => [
                'producto_id' => isset($filtros['producto_id']) ? (int) $filtros['producto_id'] : null,
                'tipo' => $filtros['tipo'] ?? '',
                'desde' => $filtros['desde'] ?? '',
                'hasta' => $filtros['hasta'] ?? '',
            ],
            'tipos' => TipoMovimientoInventario::options(),
            'productos' => Producto::query()
                ->orderBy('nombre')
                ->limit(2000)
                ->get(['id', 'nombre', 'sku', 'stock', 'stock_minimo', 'modalidad_venta'])
                ->map(fn (Producto $p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'sku' => $p->sku,
                    'stock' => (int) $p->stock,
                    'stock_minimo' => (int) $p->stock_minimo,
                    'modalidad_venta' => $p->modalidad_venta,
                ]),
            'resumen' => [
                'productos_inventario' => (clone $inventario)->count(),
                'unidades' => (int) (clone $inventario)->where('stock', '>', 0)->sum('stock'),
                'agotados' => (clone $inventario)->where('stock', '<=', 0)->where('estado_publicacion', EstadoPublicacion::Publicado->value)->count(),
                'bajo_minimo' => ProductoController::bajoMinimo(Producto::query())->count(),
                'movimientos_hoy' => InventarioMovimiento::query()
                    ->where('created_at', '>=', Carbon::now($zona)->startOfDay()->utc())
                    ->count(),
            ],
            'bajo_minimo' => $bajoMinimo,
            'agotados' => $agotados,
        ]);
    }

    public function store(AjusteInventarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $producto = Producto::query()->findOrFail($datos['producto_id']);
        $usuario = $request->user();
        $cantidad = (int) $datos['cantidad'];
        $motivo = $datos['motivo'];

        $movimiento = match ($datos['operacion']) {
            'entrada' => $this->inventario->entrada($producto, $cantidad, $usuario, $motivo),
            'salida' => $this->inventario->salida($producto, $cantidad, $usuario, $motivo),
            'ajuste' => $this->inventario->ajustar($producto, $cantidad, $usuario, $motivo),
            'establecer' => $this->inventario->establecer($producto, $cantidad, $usuario, $motivo),
        };

        if ($movimiento === null) {
            return back()->with('success', 'Las existencias de «'.$producto->nombre.'» ya eran '.$cantidad.'. No se registró movimiento.');
        }

        return back()->with('success', 'Existencias de «'.$producto->nombre.'»: '.$movimiento->stock_anterior.' → '.$movimiento->stock_nuevo.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenProducto(Producto $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'sku' => $p->sku,
            'stock' => (int) $p->stock,
            'stock_minimo' => (int) $p->stock_minimo,
            'imagen' => $p->imagenPrincipalUrl() ?? ImagenUrl::resolver($p->imagen_principal),
        ];
    }
}
