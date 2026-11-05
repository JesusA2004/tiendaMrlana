<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Http\Controllers\Admin\Concerns\PresentaPedidos;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoEstatusHistorial;
use App\Models\PedidoItem;
use App\Models\User;
use App\Services\Pedidos\PedidoWorkflow;
use App\Support\TextoPlano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de pedidos (admin y vendedor). Todas las transiciones pasan por PedidoWorkflow;
 * los gates se validan en la ruta y dentro del workflow.
 */
class PedidoController extends Controller
{
    use PresentaPedidos;

    public function __construct(private PedidoWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => trim((string) $request->query('buscar', '')),
            'estatus' => (string) $request->query('estatus', ''),
            'pago' => (string) $request->query('pago', ''),
            'logistico' => (string) $request->query('logistico', ''),
            'metodo' => (string) $request->query('metodo', ''),
        ];

        $query = Pedido::query()
            ->with('direccion:id,municipio,estado,codigo_postal')
            ->withCount('items')
            ->when($filtros['buscar'] !== '', function (Builder $q) use ($filtros) {
                $termino = '%'.str_replace(['%', '_'], ['\%', '\_'], $filtros['buscar']).'%';
                $q->where(fn (Builder $s) => $s
                    ->where('folio', 'like', $termino)
                    ->orWhere('nombre_cliente', 'like', $termino)
                    ->orWhere('correo_cliente', 'like', $termino)
                    ->orWhere('telefono_cliente', 'like', $termino));
            })
            ->when(in_array($filtros['pago'], EstadoPago::values(), true), fn (Builder $q) => $q->where('estado_pago', $filtros['pago']))
            ->when(in_array($filtros['logistico'], EstadoLogistico::values(), true), fn (Builder $q) => $q->where('estado_logistico', $filtros['logistico']))
            ->when(in_array($filtros['metodo'], MetodoPagoClave::values(), true), fn (Builder $q) => $q->where('metodo_pago', $filtros['metodo']));

        // Conteo por estatus general con el resto de filtros aplicados (chips de estatus).
        $conteos = (clone $query)
            ->reorder()
            ->selectRaw('estatus, count(*) as total')
            ->groupBy('estatus')
            ->pluck('total', 'estatus');

        $pedidos = $query
            ->when(in_array($filtros['estatus'], EstatusPedido::values(), true), fn (Builder $q) => $q->where('estatus', $filtros['estatus']))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Pedido $p) => $this->pedidoFila($p));

        return Inertia::render('Admin/Pedidos/Index', [
            'pedidos' => $pedidos,
            'filtros' => $filtros,
            'opciones' => $this->opcionesFiltros(),
            'conteos' => $conteos,
            'totalSinEstatus' => (int) $conteos->sum(),
        ]);
    }

    public function show(Request $request, Pedido $pedido): Response
    {
        /** @var User $user */
        $user = $request->user();

        $pedido->load([
            'user:id,name,email',
            'direccion',
            'items.producto' => fn ($q) => $q->select('id', 'nombre', 'slug', 'imagen_principal', 'stock', 'modalidad_venta'),
            'items.producto.imagenes:id,producto_id,ruta,principal,orden',
            'pagos' => fn ($q) => $q->latest('id'),
            'pagos.metodoPago:id,nombre,clave',
            'pagos.revisadoPor:id,name',
            'pagos.comprobantes',
            'historial' => fn ($q) => $q->latest('id'),
            'historial.user:id,name,rol',
            'reglaEnvio:id,nombre,tipo,dias_estimados',
            'canceladoPor:id,name',
            'envioCotizadoPor:id,name',
        ]);

        $pedido->load(['opcionesEnvio.creadoPor:id,name']);
        $pagoActual = $pedido->pagos->first();
        $direccion = $pedido->direccionEntrega();
        $linea1 = $direccion
            ? trim(($direccion['calle'] ?? '').' '.($direccion['numero_exterior'] ?? '').(! empty($direccion['numero_interior']) ? ' Int. '.$direccion['numero_interior'] : ''))
            : null;

        return Inertia::render('Admin/Pedidos/Show', [
            'pedido' => [
                ...$this->pedidoFila($pedido),
                'descuento' => (float) $pedido->descuento,
                'impuesto' => (float) $pedido->impuesto,
                'cupon_codigo' => $pedido->cupon_codigo,
                'notas_cliente' => $pedido->notas_cliente,
                'motivo_cancelacion' => $pedido->motivo_cancelacion,
                'cancelado_por' => $pedido->canceladoPor?->name,
                'cancelado_en' => $pedido->cancelado_en?->toIso8601String(),
                'envio_cotizado_por' => $pedido->envioCotizadoPor?->name,
                'envio_cotizado_en' => $pedido->envio_cotizado_en?->toIso8601String(),
                'pagado_en' => $pedido->pagado_en?->toIso8601String(),
                'preparando_en' => $pedido->preparando_en?->toIso8601String(),
                'listo_envio_en' => $pedido->listo_envio_en?->toIso8601String(),
                'entregado_en' => $pedido->entregado_en?->toIso8601String(),
                'regla_envio' => $pedido->reglaEnvio ? [
                    'nombre' => $pedido->reglaEnvio->nombre,
                    'dias_estimados' => $pedido->reglaEnvio->dias_estimados,
                ] : null,
                'cuenta' => $pedido->user ? ['id' => $pedido->user->id, 'name' => $pedido->user->name, 'email' => $pedido->user->email] : null,
                // Copia guardada al comprar: no cambia si el cliente edita su dirección.
                'direccion' => $direccion ? [
                    'nombre_receptor' => $direccion['nombre_receptor'] ?? null,
                    'telefono' => $direccion['telefono'] ?? null,
                    'linea1' => $linea1,
                    'colonia' => $direccion['colonia'] ?? null,
                    'municipio' => $direccion['municipio'] ?? null,
                    'estado' => $direccion['estado'] ?? null,
                    'codigo_postal' => $direccion['codigo_postal'] ?? null,
                    'referencias' => $direccion['referencias'] ?? null,
                ] : null,
                'opciones_envio' => collect($this->opcionesEnvioDatos($pedido))->map(fn (array $o) => [
                    ...$o,
                    'creado_por' => $pedido->opcionesEnvio->firstWhere('id', $o['id'])?->creadoPor?->name,
                ])->values(),
                'url_rastreo' => $pedido->urlRastreo(),
                'items' => $pedido->items->map(fn (PedidoItem $item) => [
                    'id' => $item->id,
                    'nombre' => $item->nombre,
                    'sku' => $item->sku,
                    'cantidad' => (int) $item->cantidad,
                    'precio_unitario' => (float) $item->precio_unitario,
                    'subtotal' => (float) $item->subtotal,
                    'imagen' => $item->producto?->imagenPrincipalUrl(),
                    'producto_id' => $item->producto_id,
                ])->values(),
                'pagos' => $pedido->pagos->map(fn (Pago $p) => $this->pagoDatos($p))->values(),
                'historial' => $pedido->historial->map(fn (PedidoEstatusHistorial $h) => [
                    'id' => $h->id,
                    'tipo' => $h->tipo,
                    'estatus' => $h->estatus,
                    'estatus_label' => EstatusPedido::tryFrom((string) $h->estatus)?->label() ?? $h->estatus,
                    'estatus_color' => EstatusPedido::tryFrom((string) $h->estatus)?->color() ?? 'neutral',
                    'estado_anterior' => $h->estado_anterior,
                    'comentario' => $h->comentario,
                    'visible_cliente' => (bool) $h->visible_cliente,
                    'usuario' => $h->user?->name,
                    'usuario_rol' => $h->user?->rol,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->values(),
            ],
            'pagoActualId' => $pagoActual?->id,
            'acciones' => $this->workflow->accionesDisponibles($pedido, $user),
            'paqueterias' => $this->paqueteriasActivas(),
            'puedeGestionarPaqueterias' => $user->can('gestionar-configuracion'),
            'documentos' => [
                'pdf' => Route::has('admin.pedidos.pdf') ? route('admin.pedidos.pdf', $pedido) : null,
                'hoja_empaque' => Route::has('admin.pedidos.hoja-empaque') ? route('admin.pedidos.hoja-empaque', $pedido) : null,
            ],
        ]);
    }

    // ---------------------------------------------------------------- acciones del workflow

    public function cotizarEnvio(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'monto' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->cotizarEnvio($pedido, round((float) $data['monto'], 2), $request->user(), TextoPlano::limpiar($data['nota'] ?? null));

        return back()->with('success', 'Envío cotizado. El total del pedido se actualizó y el cliente ya puede pagar.');
    }

    /** Envía (o reemplaza) las opciones de envío para que el cliente elija. */
    public function opcionesEnvio(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(self::reglasOpcionesEnvio(), [
            'opciones.required' => 'Agrega al menos una opción de envío.',
            'opciones.min' => 'Agrega al menos una opción de envío.',
            'opciones.max' => 'Envía como máximo '.PedidoWorkflow::MAX_OPCIONES_ENVIO.' opciones.',
            'opciones.*.paqueteria_id.required' => 'Elige la paquetería.',
            'opciones.*.paqueteria_id.exists' => 'Elige una paquetería activa.',
            'opciones.*.servicio.required' => 'Indica el servicio.',
            'opciones.*.costo.required' => 'Captura el costo.',
        ]);

        $opciones = collect($data['opciones'])->map(fn (array $o) => [
            'paqueteria_id' => (int) $o['paqueteria_id'],
            'servicio' => TextoPlano::linea($o['servicio']),
            'costo' => round((float) $o['costo'], 2),
            'dias_min' => isset($o['dias_min']) ? (int) $o['dias_min'] : null,
            'dias_max' => isset($o['dias_max']) ? (int) $o['dias_max'] : null,
            'tiempo_texto' => TextoPlano::limpiar($o['tiempo_texto'] ?? null, false),
            'notas' => TextoPlano::limpiar($o['notas'] ?? null),
            'recomendada' => (bool) ($o['recomendada'] ?? false),
        ])->values()->all();

        $reemplazo = $pedido->opcionesEnvio()->exists();
        $this->workflow->enviarOpcionesEnvio($pedido, $request->user(), $opciones, TextoPlano::limpiar($data['motivo'] ?? null, false));

        return back()->with('success', $reemplazo
            ? 'Cotización actualizada. Le avisamos al cliente para que elija de nuevo.'
            : 'Opciones enviadas. Le avisamos al cliente para que elija su envío.');
    }

    /** @return array<string, mixed> */
    public static function reglasOpcionesEnvio(): array
    {
        return [
            'opciones' => ['required', 'array', 'min:1', 'max:'.PedidoWorkflow::MAX_OPCIONES_ENVIO],
            'opciones.*.paqueteria_id' => ['required', 'integer', Rule::exists('paqueterias', 'id')->where('activo', true)],
            'opciones.*.servicio' => ['required', 'string', 'max:120'],
            'opciones.*.costo' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'opciones.*.dias_min' => ['nullable', 'integer', 'min:0', 'max:365'],
            'opciones.*.dias_max' => ['nullable', 'integer', 'min:0', 'max:365'],
            'opciones.*.tiempo_texto' => ['nullable', 'string', 'max:120'],
            'opciones.*.notas' => ['nullable', 'string', 'max:500'],
            'opciones.*.recomendada' => ['nullable', 'boolean'],
            'motivo' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function iniciarPreparacion(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->workflow->iniciarPreparacion($pedido, $request->user());

        return back()->with('success', 'Pedido en preparación.');
    }

    public function listoEnvio(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->workflow->marcarListoEnvio($pedido, $request->user());

        return back()->with('success', 'Pedido listo para envío.');
    }

    public function listoRecoger(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->workflow->marcarListoRecoger($pedido, $request->user());

        return back()->with('success', 'Pedido listo para recoger en tienda.');
    }

    public function registrarEnvio(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(self::reglasEnvio());

        $this->workflow->registrarEnvio(
            $pedido,
            $request->user(),
            TextoPlano::linea($data['paqueteria']),
            TextoPlano::linea($data['guia']),
            $data['fecha_envio'] ?? null,
        );

        return back()->with('success', 'Envío registrado con guía '.$data['guia'].'.');
    }

    public function confirmarEntrega(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->confirmarEntrega(
            $pedido,
            $request->user(),
            isset($data['monto_recibido']) ? round((float) $data['monto_recibido'], 2) : null,
            TextoPlano::limpiar($data['notas'] ?? null),
        );

        return back()->with('success', 'Entrega confirmada.');
    }

    public function incidencia(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(['nota' => ['required', 'string', 'min:3', 'max:1000']]);

        $this->workflow->registrarIncidencia($pedido, $request->user(), (string) TextoPlano::limpiar($data['nota']));

        return back()->with('success', 'Incidencia registrada.');
    }

    public function cancelar(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [
            'motivo.required' => 'Indica el motivo de la cancelación.',
        ]);

        $this->workflow->cancelar($pedido, $request->user(), (string) TextoPlano::limpiar($data['motivo']));

        return back()->with('success', 'Pedido cancelado y existencias liberadas.');
    }

    public function reembolso(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate(['nota' => ['nullable', 'string', 'max:500']]);

        $this->workflow->registrarReembolso($pedido, $request->user(), TextoPlano::limpiar($data['nota'] ?? null));

        return back()->with('success', 'Reembolso manual registrado.');
    }

    public function nota(Request $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validate([
            'nota' => ['required', 'string', 'max:1000'],
            'visible_cliente' => ['nullable', 'boolean'],
        ]);

        $this->workflow->agregarNota($pedido, $request->user(), (string) TextoPlano::limpiar($data['nota']), $request->boolean('visible_cliente'));

        return back()->with('success', $request->boolean('visible_cliente') ? 'Nota agregada (visible para el cliente).' : 'Nota interna agregada.');
    }

    /** @return array<string, mixed> */
    public static function reglasEnvio(string $prefijo = ''): array
    {
        return [
            $prefijo.'paqueteria' => ['required', 'string', 'max:80'],
            $prefijo.'guia' => ['required', 'string', 'max:120'],
            $prefijo.'fecha_envio' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Mexico_City')->addDay()->toDateString()],
        ];
    }
}
