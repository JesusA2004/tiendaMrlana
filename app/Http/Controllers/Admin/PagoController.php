<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPagoRegistro;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Http\Controllers\Admin\Concerns\PresentaPedidos;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Services\Pedidos\PedidoWorkflow;
use App\Support\TextoPlano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bandeja de pagos (gate revisar-pagos): transferencias en revisión, efectivo por confirmar
 * e historial (incluye pagos Openpay históricos, sólo lectura).
 */
class PagoController extends Controller
{
    use PresentaPedidos;

    public const TABS = ['revision', 'efectivo', 'historial'];

    public function __construct(private PedidoWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'revision';
        $buscar = trim((string) $request->query('buscar', ''));
        $estatus = (string) $request->query('estatus', '');
        $tipo = (string) $request->query('tipo', '');

        $base = Pago::query()
            ->with([
                'pedido:id,folio,nombre_cliente,correo_cliente,telefono_cliente,total,saldo_pendiente,envio,estatus,estado_pago,metodo_pago,envio_estado,vence_en,created_at',
                'metodoPago:id,nombre,clave',
                'revisadoPor:id,name',
            ])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $termino = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';
                $q->where(fn (Builder $s) => $s
                    ->where('referencia_cliente', 'like', $termino)
                    ->orWhere('referencia_externa', 'like', $termino)
                    ->orWhereHas('pedido', fn (Builder $p) => $p
                        ->where('folio', 'like', $termino)
                        ->orWhere('nombre_cliente', 'like', $termino)
                        ->orWhere('correo_cliente', 'like', $termino)));
            });

        $query = match ($tab) {
            'revision' => $this->enRevision(clone $base)->with('comprobantes')->oldest('updated_at'),
            'efectivo' => $this->efectivoPendiente(clone $base)->oldest('id'),
            default => (clone $base)
                ->when($estatus !== '', fn (Builder $q) => $q->where('estatus', $estatus))
                ->when($tipo !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s
                    ->where('tipo', $tipo)
                    ->orWhereHas('metodoPago', fn (Builder $m) => $m->where('clave', $tipo))))
                ->with('comprobantes')
                ->latest('id'),
        };

        $pagos = $query
            ->paginate($tab === 'historial' ? 20 : 12)
            ->withQueryString()
            ->through(fn (Pago $pago) => [
                ...$this->pagoDatos($pago),
                'pedido' => $pago->pedido ? [
                    'id' => $pago->pedido->id,
                    'folio' => $pago->pedido->folio,
                    'nombre_cliente' => $pago->pedido->nombre_cliente,
                    'correo_cliente' => $pago->pedido->correo_cliente,
                    'telefono_cliente' => $pago->pedido->telefono_cliente,
                    'total' => (float) $pago->pedido->total,
                    'envio' => (float) $pago->pedido->envio,
                    'saldo_pendiente' => (float) $pago->pedido->saldo_pendiente,
                    'estatus' => $this->etiqueta(EstatusPedido::class, $pago->pedido->estatus),
                    'metodo_pago' => $pago->pedido->metodo_pago,
                    'envio_por_cotizar' => $pago->pedido->envioPendiente(),
                    'vence_en' => $pago->pedido->vence_en?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('Admin/Pagos/Index', [
            'tab' => $tab,
            'pagos' => $pagos,
            'seleccionado' => $request->integer('pago') ?: null,
            'conteos' => [
                'revision' => $this->enRevision(Pago::query())->count(),
                'efectivo' => $this->efectivoPendiente(Pago::query())->count(),
            ],
            'filtros' => ['buscar' => $buscar, 'estatus' => $estatus, 'tipo' => $tipo],
            'opciones' => [
                'estatus' => [
                    ...EstadoPagoRegistro::options(),
                    // Estatus históricos de Openpay.
                    ['value' => 'pagado', 'label' => 'Pagado (Openpay)', 'color' => 'success'],
                    ['value' => 'procesando', 'label' => 'Procesando (Openpay)', 'color' => 'neutral'],
                ],
                'tipo' => MetodoPagoClave::options(),
            ],
        ]);
    }

    public function aprobar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate(['notas' => ['nullable', 'string', 'max:500']]);

        $pedido = $this->workflow->aprobarPago($pago, $request->user(), TextoPlano::limpiar($data['notas'] ?? null));

        return back()->with('success', 'Pago del pedido '.$pedido->folio.' aprobado. El pedido quedó confirmado.');
    }

    public function rechazar(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => 'Indica el motivo del rechazo; se le mostrará al cliente.',
            'motivo.min' => 'Describe el motivo con al menos 5 caracteres.',
        ]);

        $pedido = $this->workflow->rechazarPago($pago, $request->user(), (string) TextoPlano::limpiar($data['motivo']));

        return back()->with('success', 'Comprobante del pedido '.$pedido->folio.' rechazado. El cliente puede subir uno nuevo.');
    }

    public function confirmarEfectivo(Request $request, Pago $pago): RedirectResponse
    {
        $data = $request->validate([
            'monto_recibido' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $pedido = $this->workflow->confirmarEfectivo($pago, $request->user(), round((float) $data['monto_recibido'], 2), TextoPlano::limpiar($data['notas'] ?? null));

        return back()->with('success', 'Efectivo recibido en el pedido '.$pedido->folio.'.');
    }

    private function enRevision(Builder $q): Builder
    {
        return $q->where('estatus', EstadoPagoRegistro::EnRevision->value);
    }

    private function efectivoPendiente(Builder $q): Builder
    {
        return $q->where('estatus', EstadoPagoRegistro::Pendiente->value)
            ->whereIn('tipo', [MetodoPagoClave::EfectivoPresencial->value, MetodoPagoClave::EfectivoContraEntrega->value])
            ->whereHas('pedido', fn (Builder $p) => $p->whereNotIn('estatus', [
                EstatusPedido::Cancelado->value,
                EstatusPedido::Vencido->value,
                EstatusPedido::Reembolsado->value,
            ]));
    }
}
