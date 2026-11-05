<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstadoPagoRegistro;
use App\Enums\EstadoPublicacion;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadVenta;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero operativo. Sólo indicadores (sin gráficas): KPIs, flujo y bandeja «Requiere atención».
 * Admin ve todo; vendedor sólo lo operativo; el cliente se redirige a «Mis pedidos».
 */
class DashboardController extends Controller
{
    public const PERIODOS = [
        'hoy' => 'Hoy',
        '7d' => 'Últimos 7 días',
        '30d' => 'Últimos 30 días',
        'mes' => 'Este mes',
    ];

    /** Pagos que cuentan como venta confirmada (incluye el histórico «pagado» de Openpay). */
    private const PAGOS_CONFIRMADOS = [EstadoPagoRegistro::Aprobado->value, 'pagado'];

    public function __invoke(Request $request, ConfiguracionTienda $config): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Gate::forUser($user)->allows('operar-pedidos')) {
            return redirect()->route('cliente.pedidos.index');
        }

        $periodo = array_key_exists((string) $request->query('periodo'), self::PERIODOS)
            ? (string) $request->query('periodo')
            : '7d';
        [$desde, $hasta] = $this->rango($periodo);

        $esAdmin = Gate::forUser($user)->allows('revisar-pagos');

        $operacion = $this->operacion($desde, $hasta);

        return Inertia::render('Dashboard', [
            'periodo' => $periodo,
            'periodos' => collect(self::PERIODOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'rango' => ['desde' => $desde->toIso8601String(), 'hasta' => $hasta->toIso8601String()],
            'esAdmin' => $esAdmin,
            'operacion' => $operacion,
            'flujo' => $this->flujo(),
            'comercial' => $esAdmin ? $this->comercial($desde, $hasta) : null,
            'catalogo' => Gate::forUser($user)->allows('gestionar-catalogo') ? $this->catalogo() : null,
            'atencion' => $this->atencion($esAdmin, $config),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function rango(string $periodo): array
    {
        // El negocio opera en hora de México; los timestamps se guardan en la zona de la app.
        $ahora = CarbonImmutable::now('America/Mexico_City');

        $desde = match ($periodo) {
            'hoy' => $ahora->startOfDay(),
            '30d' => $ahora->subDays(29)->startOfDay(),
            'mes' => $ahora->startOfMonth(),
            default => $ahora->subDays(6)->startOfDay(),
        };

        $zona = config('app.timezone');

        return [$desde->setTimezone($zona), $ahora->setTimezone($zona)];
    }

    private function activos(): Builder
    {
        return Pedido::query()->whereNotIn('estatus', [
            EstatusPedido::Cancelado->value,
            EstatusPedido::Vencido->value,
            EstatusPedido::Reembolsado->value,
        ]);
    }

    /** @return array<string, int> */
    private function operacion(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $activos = $this->activos();

        return [
            'pendientes_pago' => (clone $activos)->where('estatus', EstatusPedido::PendientePago->value)->count(),
            'pagos_por_revisar' => (clone $activos)->where('estado_pago', EstadoPago::EnRevision->value)->count(),
            'por_cotizar' => (clone $activos)->where('envio_estado', EstadoEnvio::PorCotizar->value)->count(),
            'esperando_eleccion' => (clone $activos)->where('envio_estado', EstadoEnvio::OpcionesEnviadas->value)->count(),
            'por_preparar' => (clone $activos)->where('estatus', EstatusPedido::Confirmado->value)
                ->where('estado_logistico', EstadoLogistico::Pendiente->value)->count(),
            'en_preparacion' => (clone $activos)->where('estado_logistico', EstadoLogistico::EnPreparacion->value)->count(),
            'listos_envio' => (clone $activos)->whereIn('estado_logistico', [EstadoLogistico::ListoEnvio->value, EstadoLogistico::ListoRecoger->value])->count(),
            'enviados' => (clone $activos)->where('estado_logistico', EstadoLogistico::Enviado->value)->count(),
            'incidencias' => (clone $activos)->where('estado_logistico', EstadoLogistico::Incidencia->value)->count(),
            'entregados_periodo' => Pedido::query()->where('estado_logistico', EstadoLogistico::Entregado->value)
                ->whereBetween('entregado_en', [$desde, $hasta])->count(),
        ];
    }

    /**
     * Etapas del flujo de pedidos activos (barra de progreso segmentada).
     *
     * @return list<array{clave: string, label: string, valor: int, tono: string}>
     */
    private function flujo(): array
    {
        $conteos = $this->activos()
            ->where('estado_logistico', '!=', EstadoLogistico::Entregado->value)
            ->selectRaw('estatus, count(*) as total')
            ->groupBy('estatus')
            ->pluck('total', 'estatus');

        $etapas = [
            EstatusPedido::PendienteCotizacion,
            EstatusPedido::EleccionEnvio,
            EstatusPedido::PendientePago,
            EstatusPedido::PagoEnRevision,
            EstatusPedido::Confirmado,
            EstatusPedido::EnPreparacion,
            EstatusPedido::ListoEnvio,
            EstatusPedido::Enviado,
        ];

        return array_map(fn (EstatusPedido $e) => [
            'clave' => $e->value,
            'label' => $e->label(),
            'valor' => (int) ($conteos[$e->value] ?? 0),
            'tono' => $e->color(),
        ], $etapas);
    }

    /** @return array<string, int|float> */
    private function comercial(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ventas = Pago::query()
            ->whereIn('estatus', self::PAGOS_CONFIRMADOS)
            ->whereBetween('pagado_en', [$desde, $hasta]);

        $creados = Pedido::query()->whereBetween('created_at', [$desde, $hasta]);

        return [
            'ventas_confirmadas' => round((float) (clone $ventas)->sum('monto'), 2),
            'pagos_confirmados' => (clone $ventas)->count(),
            'importe_creados' => round((float) (clone $creados)->whereNotIn('estatus', [EstatusPedido::Cancelado->value, EstatusPedido::Vencido->value])->sum('total'), 2),
            'pedidos_creados' => (clone $creados)->count(),
            'cancelados_periodo' => Pedido::query()->where('estatus', EstatusPedido::Cancelado->value)->whereBetween('cancelado_en', [$desde, $hasta])->count(),
            'saldo_contra_entrega' => round((float) $this->activos()
                ->where('metodo_pago', MetodoPagoClave::EfectivoContraEntrega->value)
                ->where('estado_pago', EstadoPago::ContraEntrega->value)
                ->sum('saldo_pendiente'), 2),
            'pedidos_contra_entrega' => $this->activos()
                ->where('metodo_pago', MetodoPagoClave::EfectivoContraEntrega->value)
                ->where('estado_pago', EstadoPago::ContraEntrega->value)
                ->count(),
            'efectivo_por_confirmar' => $this->activos()
                ->where('metodo_pago', MetodoPagoClave::EfectivoPresencial->value)
                ->where('estado_pago', EstadoPago::Pendiente->value)
                ->whereNotIn('envio_estado', EstadoEnvio::pendientes())
                ->count(),
        ];
    }

    /** @return array<string, int> */
    private function catalogo(): array
    {
        return [
            'publicados' => Producto::query()->publicados()->count(),
            'sin_existencias' => Producto::query()->publicados()
                ->where('modalidad_venta', ModalidadVenta::Inventario->value)
                ->where(fn (Builder $q) => $q->whereNull('stock')->orWhere('stock', '<=', 0))
                ->count(),
            'pendientes_aprobacion' => Producto::query()->where('estado_publicacion', EstadoPublicacion::PendienteAprobacion->value)->count(),
        ];
    }

    /**
     * Bandeja «Requiere atención»: lo más urgente primero, con enlace directo.
     *
     * @return list<array{clave: string, titulo: string, detalle: string, href: string, tono: string, cantidad: int}>
     */
    private function atencion(bool $esAdmin, ConfiguracionTienda $config): array
    {
        $items = [];
        $activos = $this->activos();

        if ($esAdmin) {
            if (! $config->bancoConfigurado()) {
                $items[] = [
                    'clave' => 'banco',
                    'titulo' => 'Datos bancarios pendientes de configurar',
                    'detalle' => 'Sin banco, beneficiario y CLABE válida los clientes no pueden pagar por transferencia.',
                    'href' => route('admin.configuraciones.index', ['tab' => 'banco']),
                    'tono' => 'danger',
                    'cantidad' => 1,
                ];
            }

            $enRevision = Pago::query()
                ->where('estatus', EstadoPagoRegistro::EnRevision->value)
                ->with('pedido:id,folio,nombre_cliente')
                ->oldest('updated_at')
                ->limit(5)
                ->get(['id', 'pedido_id', 'monto', 'updated_at']);

            foreach ($enRevision as $pago) {
                $items[] = [
                    'clave' => 'pago-'.$pago->id,
                    'titulo' => 'Comprobante por revisar · '.$pago->pedido?->folio,
                    'detalle' => trim(($pago->pedido?->nombre_cliente ?? '').' · recibido '.$pago->updated_at?->diffForHumans()),
                    'href' => route('admin.pagos.index', ['tab' => 'revision', 'pago' => $pago->id]),
                    'tono' => 'info',
                    'cantidad' => 1,
                ];
            }

            $efectivo = (clone $activos)
                ->where('metodo_pago', MetodoPagoClave::EfectivoPresencial->value)
                ->where('estado_pago', EstadoPago::Pendiente->value)
                ->whereNotIn('envio_estado', EstadoEnvio::pendientes())
                ->count();

            if ($efectivo > 0) {
                $items[] = [
                    'clave' => 'efectivo',
                    'titulo' => 'Pagos en efectivo por confirmar',
                    'detalle' => 'Confirma el monto recibido en tienda para liberar la preparación.',
                    'href' => route('admin.pagos.index', ['tab' => 'efectivo']),
                    'tono' => 'warning',
                    'cantidad' => $efectivo,
                ];
            }

            $porVencer = (clone $activos)
                ->where('estatus', EstatusPedido::PendientePago->value)
                ->whereNotNull('vence_en')
                ->whereBetween('vence_en', [now(), now()->addHours(12)])
                ->count();

            if ($porVencer > 0) {
                $items[] = [
                    'clave' => 'por-vencer',
                    'titulo' => 'Pedidos por vencer en menos de 12 h',
                    'detalle' => 'Sin pago registrado; considera contactar al cliente.',
                    'href' => route('admin.pedidos.index', ['estatus' => EstatusPedido::PendientePago->value]),
                    'tono' => 'warning',
                    'cantidad' => $porVencer,
                ];
            }

            $pendientesAprobacion = Producto::query()->where('estado_publicacion', EstadoPublicacion::PendienteAprobacion->value)->count();

            if ($pendientesAprobacion > 0) {
                $items[] = [
                    'clave' => 'catalogo',
                    'titulo' => 'Productos esperando aprobación',
                    'detalle' => 'Revisa precio, existencias e imágenes antes de publicarlos.',
                    'href' => '/admin/productos?estado=pendiente_aprobacion',
                    'tono' => 'brand',
                    'cantidad' => $pendientesAprobacion,
                ];
            }
        }

        $porCotizar = (clone $activos)->where('envio_estado', EstadoEnvio::PorCotizar->value)->count();

        if ($porCotizar > 0) {
            $items[] = [
                'clave' => 'cotizar',
                'titulo' => 'Envíos por cotizar',
                'detalle' => 'El cliente espera el costo de envío para poder pagar.',
                'href' => route('admin.envios.index', ['tab' => 'por_cotizar']),
                'tono' => 'warning',
                'cantidad' => $porCotizar,
            ];
        }

        $esperandoEleccion = (clone $activos)->where('envio_estado', EstadoEnvio::OpcionesEnviadas->value)->count();

        if ($esperandoEleccion > 0) {
            $items[] = [
                'clave' => 'eleccion-envio',
                'titulo' => 'Esperando elección del cliente',
                'detalle' => 'Ya tienen opciones de paquetería; si tardan, contacta al cliente o re-cotiza.',
                'href' => route('admin.envios.index', ['tab' => 'esperando_eleccion']),
                'tono' => 'info',
                'cantidad' => $esperandoEleccion,
            ];
        }

        $porPreparar = (clone $activos)->where('estatus', EstatusPedido::Confirmado->value)
            ->where('estado_logistico', EstadoLogistico::Pendiente->value)->count();

        if ($porPreparar > 0) {
            $items[] = [
                'clave' => 'preparar',
                'titulo' => 'Pedidos confirmados por preparar',
                'detalle' => 'Pago confirmado: ya puedes iniciar la preparación.',
                'href' => route('admin.envios.index', ['tab' => 'por_preparar']),
                'tono' => 'success',
                'cantidad' => $porPreparar,
            ];
        }

        $listos = (clone $activos)->where('estado_logistico', EstadoLogistico::ListoEnvio->value)->count();

        if ($listos > 0) {
            $items[] = [
                'clave' => 'listos',
                'titulo' => 'Listos para envío sin guía',
                'detalle' => 'Registra paquetería y número de guía.',
                'href' => route('admin.envios.index', ['tab' => 'listos']),
                'tono' => 'info',
                'cantidad' => $listos,
            ];
        }

        $incidencias = (clone $activos)->where('estado_logistico', EstadoLogistico::Incidencia->value)->count();

        if ($incidencias > 0) {
            $items[] = [
                'clave' => 'incidencias',
                'titulo' => 'Pedidos con incidencia',
                'detalle' => 'Requieren seguimiento con el cliente o la paquetería.',
                'href' => route('admin.pedidos.index', ['logistico' => EstadoLogistico::Incidencia->value]),
                'tono' => 'danger',
                'cantidad' => $incidencias,
            ];
        }

        return $items;
    }
}
