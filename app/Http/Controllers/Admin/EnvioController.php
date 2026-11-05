<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstatusPedido;
use App\Enums\ModalidadEntrega;
use App\Exceptions\DominioException;
use App\Http\Controllers\Admin\Concerns\PresentaPedidos;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Pedidos\PedidoWorkflow;
use App\Support\TextoPlano;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tablero logístico: por cotizar → por preparar → en preparación → listos → enviados.
 */
class EnvioController extends Controller
{
    use PresentaPedidos;

    public const TABS = [
        'por_cotizar' => 'Por cotizar',
        'esperando_eleccion' => 'Esperando al cliente',
        'por_preparar' => 'Por preparar',
        'en_preparacion' => 'En preparación',
        'listos' => 'Listos para envío',
        'enviados' => 'Enviados sin entregar',
    ];

    public function __construct(private PedidoWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'por_cotizar';
        $buscar = trim((string) $request->query('buscar', ''));

        $pedidos = $this->consulta($tab)
            ->with(['direccion:id,municipio,estado,codigo_postal', 'reglaEnvio:id,nombre', 'opcionesEnvio'])
            ->withCount('items')
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $termino = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';
                $q->where(fn (Builder $s) => $s->where('folio', 'like', $termino)->orWhere('nombre_cliente', 'like', $termino));
            })
            ->orderBy($tab === 'enviados' ? 'enviado_en' : 'updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Pedido $p) => [
                ...$this->pedidoFila($p),
                'regla_envio' => $p->reglaEnvio?->nombre,
                'opciones_envio' => $this->opcionesEnvioDatos($p),
                'acciones' => $this->workflow->accionesDisponibles($p, $user),
            ]);

        return Inertia::render('Admin/Envios/Index', [
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($label, $clave) => [
                'value' => $clave,
                'label' => $label,
                'total' => $this->consulta($clave)->count(),
            ])->values(),
            'pedidos' => $pedidos,
            'filtros' => ['buscar' => $buscar],
            'puedeGestionarReglas' => Gate::forUser($user)->allows('gestionar-configuracion'),
            'paqueterias' => $this->paqueteriasActivas(),
        ]);
    }

    /**
     * Registra paquetería/guía en varios pedidos a la vez. Cada pedido pasa por el workflow;
     * los que fallan se reportan sin detener al resto.
     */
    public function registrarLote(Request $request): RedirectResponse
    {
        $request->validate([
            'envios' => ['required', 'array', 'min:1', 'max:50'],
            'envios.*.pedido_id' => ['required', 'integer', 'distinct', 'exists:pedidos,id'],
            ...PedidoController::reglasEnvio('envios.*.'),
        ], [
            'envios.*.paqueteria.required' => 'Captura la paquetería.',
            'envios.*.guia.required' => 'Captura el número de guía.',
        ]);

        $ok = [];
        $errores = [];
        $pedidos = Pedido::query()->whereIn('id', collect($request->input('envios'))->pluck('pedido_id'))->get()->keyBy('id');

        foreach ($request->input('envios') as $envio) {
            $pedido = $pedidos->get((int) $envio['pedido_id']);

            try {
                $this->workflow->registrarEnvio(
                    $pedido,
                    $request->user(),
                    TextoPlano::linea($envio['paqueteria']),
                    TextoPlano::linea($envio['guia']),
                    $envio['fecha_envio'] ?? null,
                );
                $ok[] = $pedido->folio;
            } catch (DominioException|AuthorizationException $e) {
                $errores[] = $pedido->folio.': '.$e->getMessage();
            }
        }

        $respuesta = back();

        if ($ok !== []) {
            $respuesta = $respuesta->with('success', count($ok) === 1
                ? 'Envío registrado: '.$ok[0].'.'
                : count($ok).' envíos registrados ('.implode(', ', $ok).').');
        }

        if ($errores !== []) {
            $respuesta = $respuesta->with('error', 'No se registraron: '.implode(' · ', $errores));
        }

        return $respuesta;
    }

    private function consulta(string $tab): Builder
    {
        $activos = Pedido::query()->whereNotIn('estatus', [
            EstatusPedido::Cancelado->value,
            EstatusPedido::Vencido->value,
            EstatusPedido::Reembolsado->value,
        ]);

        return match ($tab) {
            'por_preparar' => $activos->where('estatus', EstatusPedido::Confirmado->value)
                ->where('estado_logistico', EstadoLogistico::Pendiente->value),
            'en_preparacion' => $activos->where('estado_logistico', EstadoLogistico::EnPreparacion->value),
            'listos' => $activos->whereIn('estado_logistico', [EstadoLogistico::ListoEnvio->value, EstadoLogistico::ListoRecoger->value]),
            'enviados' => $activos->whereIn('estado_logistico', [EstadoLogistico::Enviado->value, EstadoLogistico::Incidencia->value]),
            'esperando_eleccion' => $activos->where('envio_estado', EstadoEnvio::OpcionesEnviadas->value),
            default => $activos->where('envio_estado', EstadoEnvio::PorCotizar->value)
                ->where('modalidad_entrega', ModalidadEntrega::EnvioDomicilio->value),
        };
    }
}
