<?php

namespace App\Http\Controllers\Cliente;

use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Http\Controllers\Cliente\Concerns\PresentaPedido;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Publico\CheckoutController;
use App\Models\Pedido;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pedidos del cliente autenticado. Sólo pedidos propios (ajenos → 404).
 */
class MisPedidosController extends Controller
{
    use PresentaPedido;

    /** Filtros visibles para el cliente (agrupan estatus generales). */
    private const FILTROS = [
        'por_pagar' => [EstatusPedido::PendienteCotizacion, EstatusPedido::EleccionEnvio, EstatusPedido::PendientePago, EstatusPedido::PagoEnRevision],
        'en_proceso' => [EstatusPedido::Confirmado, EstatusPedido::EnPreparacion, EstatusPedido::ListoEnvio, EstatusPedido::Enviado],
        'entregados' => [EstatusPedido::Entregado],
        'cerrados' => [EstatusPedido::Cancelado, EstatusPedido::Vencido, EstatusPedido::Reembolsado],
    ];

    public function __construct(private readonly ConfiguracionTienda $config) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $filtro = array_key_exists((string) $request->query('estado'), self::FILTROS) ? (string) $request->query('estado') : null;

        $base = Pedido::query()->where('user_id', $userId);

        $conteos = ['todos' => (clone $base)->count()];
        foreach (self::FILTROS as $clave => $estatus) {
            $conteos[$clave] = (clone $base)->whereIn('estatus', array_map(fn ($e) => $e->value, $estatus))->count();
        }

        $pedidos = (clone $base)
            ->when($filtro, fn (Builder $q) => $q->whereIn('estatus', array_map(fn ($e) => $e->value, self::FILTROS[$filtro])))
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->select('id', 'pedido_id', 'producto_id', 'nombre', 'cantidad')->with('producto')])
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Pedido $pedido) => [
                'id' => $pedido->id,
                'folio' => $pedido->folio,
                'created_at' => $pedido->created_at?->toIso8601String(),
                'total' => (float) $pedido->total,
                'envio_por_cotizar' => $pedido->envioPendiente(),
                'envio_esperando_eleccion' => $pedido->esperaEleccionEnvio(),
                'metodo_pago_label' => MetodoPagoClave::labelDe($pedido->metodo_pago) ?? 'Sin definir',
                'estatus' => $this->info(EstatusPedido::class, $pedido->estatus),
                'estado_pago' => $this->info(EstadoPago::class, $pedido->estado_pago),
                'estado_logistico' => $this->info(EstadoLogistico::class, $pedido->estado_logistico),
                'items_count' => (int) $pedido->items_count,
                'piezas' => (int) $pedido->items->sum('cantidad'),
                'imagenes' => $pedido->items->take(3)->map(fn ($i) => $i->producto?->imagenPrincipalUrl())->filter()->values(),
                'primer_producto' => $pedido->items->first()?->nombre,
                'requiere_accion' => ($pedido->esperaEleccionEnvio() && ! $pedido->esTerminal()) || (in_array($pedido->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value], true)
                    && $pedido->metodo_pago === MetodoPagoClave::Transferencia->value
                    && ! $pedido->envioPendiente()
                    && ! $pedido->esTerminal()),
            ]);

        return Inertia::render('Cliente/Pedidos/Index', [
            'pedidos' => $pedidos,
            'filtro' => $filtro,
            'conteos' => $conteos,
        ]);
    }

    public function show(Request $request, Pedido $pedido): Response
    {
        abort_unless((int) $pedido->user_id === (int) $request->user()->id, 404);

        return Inertia::render('Cliente/Pedidos/Show', [
            'pedido' => $this->presentarPedido($pedido),
            'instrucciones' => $this->instruccionesPago($pedido, true, $this->config),
            'urls' => [
                'pdf' => route('cliente.pedidos.pdf', $pedido),
                'comprobante' => route('cliente.pedidos.comprobantes.store', $pedido),
                'elegir_envio' => route('cliente.pedidos.envio.elegir', $pedido),
            ],
            'comprobante_reglas' => CheckoutController::reglasComprobante(),
        ]);
    }

    /**
     * El cliente elige una de las opciones de envío cotizadas (pedido ajeno → 404).
     * El workflow valida estado y pertenencia de la opción con bloqueo.
     */
    public function elegirEnvio(Request $request, Pedido $pedido, PedidoWorkflow $workflow): RedirectResponse
    {
        abort_unless((int) $pedido->user_id === (int) $request->user()->id, 404);

        $data = $request->validate(['opcion_id' => ['required', 'integer']], [
            'opcion_id.required' => 'Elige una opción de envío.',
        ]);

        $workflow->elegirOpcionEnvio($pedido, $request->user(), (int) $data['opcion_id']);

        return back()->with('success', 'Listo, elegiste tu envío. Ya puedes ver el total final y cómo pagar.');
    }
}
