<?php

namespace App\Http\Controllers\Publico;

use App\Data\DatosCheckout;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Exceptions\DominioException;
use App\Http\Controllers\Cliente\Concerns\PresentaPedido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\CheckoutRequest;
use App\Models\Direccion;
use App\Models\MetodoPago;
use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Carrito\CarritoService;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Envios\EnvioService;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout sin tarjetas: transferencia, efectivo en tienda o contra entrega.
 * El pedido lo crea PedidoWorkflow (recalcula importes, reserva stock, registra cupón).
 *
 * Idempotencia: cada visita al checkout tiene un `checkout_token` en sesión; el POST exige
 * el token vigente, toma un candado por usuario y, si el token ya generó un pedido, redirige
 * a ese pedido en lugar de crear otro (doble clic, reenvío del formulario o "atrás").
 */
class CheckoutController extends Controller
{
    use PresentaPedido;

    private const SESION_TOKEN = 'checkout_token';

    public function __construct(
        private readonly CarritoService $carrito,
        private readonly PedidoWorkflow $workflow,
        private readonly EnvioService $envios,
        private readonly ConfiguracionTienda $config,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($this->carrito->estaVacio()) {
            return redirect()->route('carrito.index')->with('error', 'Tu carrito está vacío.');
        }

        /** @var User $user */
        $user = $request->user();

        $direcciones = Direccion::query()
            ->where('user_id', $user->id)
            ->orderByDesc('predeterminada')
            ->latest('id')
            ->get();

        $modalidades = $this->envios->modalidadesDisponibles();
        $modalidad = $this->modalidadElegida($request->query('modalidad_entrega'), $modalidades);

        [$direccion, $usarNueva] = $this->direccionParaCotizar($request, $direcciones);
        $calculo = $this->carrito->resumen(
            $user,
            $modalidad,
            $modalidad === ModalidadEntrega::EnvioDomicilio->value ? $direccion : null,
        );

        if ($calculo['items'] === []) {
            return redirect()->route('carrito.index')->with('error', 'Los productos del carrito ya no están disponibles.');
        }

        $token = $request->session()->get(self::SESION_TOKEN);

        if (! is_string($token) || $token === '') {
            $token = (string) Str::uuid();
            $request->session()->put(self::SESION_TOKEN, $token);
        }

        $ultimo = Pedido::query()->where('user_id', $user->id)->latest('id')->first(['telefono_cliente']);

        return Inertia::render('Tienda/Checkout', [
            'items' => $calculo['items'],
            'cupon' => $calculo['cupon'],
            'cupon_error' => $calculo['cupon_error'],
            'resumen' => $calculo['resumen'],
            'envio' => $calculo['envio'],
            'errores' => $calculo['errores'],
            'puede_comprar' => $calculo['puede_comprar'],
            'direcciones' => $direcciones->map(fn (Direccion $d) => $this->presentarDireccion($d))->values(),
            'seleccion' => [
                'modalidad_entrega' => $modalidad,
                'direccion_id' => $usarNueva ? null : $direccion?->id,
                'nueva' => $usarNueva,
            ],
            'modalidades_entrega' => $modalidades,
            'recoger' => $this->envios->recogerDisponible() ? $this->config->recoger() : null,
            'metodos_pago' => $this->metodosCheckout($calculo, $modalidad === ModalidadEntrega::EnvioDomicilio->value ? $direccion : null),
            'cliente' => [
                'nombre' => $user->name,
                'correo' => $user->email,
                'telefono' => $ultimo?->telefono_cliente ?? $direcciones->first()?->telefono,
            ],
            'checkout_token' => $token,
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $token = (string) $data['checkout_token'];
        $claveHecho = 'checkout:hecho:'.$user->id.':'.$token;

        // Reenvío del mismo formulario ya procesado → al pedido creado (sin duplicar).
        if ($pedidoId = Cache::get($claveHecho)) {
            return redirect()->route('checkout.gracias', $pedidoId);
        }

        if (! hash_equals((string) $request->session()->get(self::SESION_TOKEN, ''), $token)) {
            return redirect()->route('checkout.index')
                ->with('error', 'Tu sesión de compra se actualizó. Revisa tu pedido y confirma de nuevo.');
        }

        if ($this->carrito->estaVacio()) {
            return redirect()->route('carrito.index')->with('error', 'Tu carrito está vacío.');
        }

        $candado = Cache::lock('checkout:usuario:'.$user->id, 30);

        if (! $candado->get()) {
            return back()->with('error', 'Ya estamos procesando tu pedido. Espera un momento.');
        }

        try {
            if ($pedidoId = Cache::get($claveHecho)) {
                return redirect()->route('checkout.gracias', $pedidoId);
            }

            $modalidad = $data['modalidad_entrega'];

            try {
                $pedido = DB::transaction(fn () => $this->workflow->crearDesdeCheckout($user, new DatosCheckout(
                    lineas: $this->carrito->lineas(),
                    metodoPago: $data['metodo_pago'],
                    modalidadEntrega: $modalidad,
                    direccion: $modalidad === ModalidadEntrega::EnvioDomicilio->value ? $this->resolverDireccion($user, $data) : null,
                    nombreCliente: trim($data['nombre_cliente']),
                    correoCliente: $data['correo_cliente'],
                    telefonoCliente: trim($data['telefono_cliente']),
                    notasCliente: filled($data['notas_cliente'] ?? null) ? trim($data['notas_cliente']) : null,
                    cuponCodigo: $this->carrito->codigoCupon(),
                )));
            } catch (DominioException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            Cache::put($claveHecho, $pedido->id, now()->addHours(6));
            $request->session()->forget(self::SESION_TOKEN);
            $this->carrito->vaciar();
        } finally {
            $candado->release();
        }

        return redirect()
            ->route('checkout.gracias', $pedido)
            ->with('success', 'Tu pedido '.$pedido->folio.' fue registrado.');
    }

    public function gracias(Request $request, Pedido $pedido): Response
    {
        Gate::authorize('view', $pedido);

        $esDueno = (int) $pedido->user_id === (int) $request->user()->id;

        return Inertia::render('Tienda/CheckoutGracias', [
            'pedido' => $this->presentarPedido($pedido),
            'instrucciones' => $this->instruccionesPago($pedido, $esDueno, $this->config),
            'es_dueno' => $esDueno,
            'urls' => [
                'pdf' => $esDueno ? route('cliente.pedidos.pdf', $pedido) : route('admin.pedidos.pdf', $pedido),
                'detalle' => $esDueno ? route('cliente.pedidos.show', $pedido) : null,
                'comprobante' => $esDueno ? route('cliente.pedidos.comprobantes.store', $pedido) : null,
                'elegir_envio' => $esDueno ? route('cliente.pedidos.envio.elegir', $pedido) : null,
            ],
            'comprobante_reglas' => self::reglasComprobante(),
        ]);
    }

    /** @return array{extensiones: list<string>, max_mb: int, accept: string} */
    public static function reglasComprobante(): array
    {
        return [
            'extensiones' => PagoComprobante::EXTENSIONES,
            'max_mb' => (int) (PagoComprobante::MAX_KB / 1024),
            'accept' => implode(',', [...array_map(fn ($e) => '.'.$e, PagoComprobante::EXTENSIONES), ...PagoComprobante::MIMES]),
        ];
    }

    // ------------------------------------------------------------------ internos

    /** @param  list<array{value: string, label: string, disponible: bool}>  $modalidades */
    private function modalidadElegida(mixed $solicitada, array $modalidades): string
    {
        $disponibles = collect($modalidades)->where('disponible', true)->pluck('value')->all();

        return is_string($solicitada) && in_array($solicitada, $disponibles, true)
            ? $solicitada
            : ModalidadEntrega::EnvioDomicilio->value;
    }

    /**
     * Dirección con la que se cotiza en vivo: guardada (direccion_id) o una nueva
     * aún no guardada (sólo estado y CP, sin persistir).
     *
     * @param  Collection<int, Direccion>  $direcciones
     * @return array{0: ?Direccion, 1: bool}
     */
    private function direccionParaCotizar(Request $request, Collection $direcciones): array
    {
        $nueva = $request->boolean('nueva') || $direcciones->isEmpty();

        if ($nueva) {
            $estado = $request->query('estado');
            $cp = $request->query('codigo_postal');

            $direccion = (is_string($estado) && $estado !== '') || (is_string($cp) && preg_match('/^\d{5}$/', $cp))
                ? new Direccion([
                    'estado' => is_string($estado) ? mb_substr($estado, 0, 120) : null,
                    'codigo_postal' => is_string($cp) && preg_match('/^\d{5}$/', $cp) ? $cp : null,
                ])
                : null;

            return [$direccion, true];
        }

        $elegida = $direcciones->firstWhere('id', (int) $request->query('direccion_id')) ?? $direcciones->first();

        return [$elegida, false];
    }

    /** @return list<array<string, mixed>> */
    private function metodosCheckout(array $calculo, ?Direccion $direccion): array
    {
        $porCotizar = (bool) ($calculo['resumen']['envio_por_cotizar'] ?? false);
        $modalidad = $calculo['envio']['modalidad'] ?? ModalidadEntrega::EnvioDomicilio->value;

        return MetodoPago::query()
            ->paraCheckout()
            ->whereIn('clave', CheckoutRequest::METODOS)
            ->orderBy('id')
            ->get()
            ->map(function (MetodoPago $m) use ($calculo, $direccion, $porCotizar, $modalidad) {
                $disponible = true;
                $motivo = null;

                if ($m->clave === MetodoPagoClave::EfectivoContraEntrega->value) {
                    $disponible = $this->envios->permiteContraEntrega($calculo['envio'], (float) $calculo['resumen']['total'], $direccion?->estado);
                    $motivo = match (true) {
                        $disponible => null,
                        $modalidad !== ModalidadEntrega::EnvioDomicilio->value => 'Sólo para envío a domicilio.',
                        $direccion === null => 'Elige tu dirección para saber si está disponible.',
                        $porCotizar => 'No disponible cuando el envío está por cotizar.',
                        default => 'No disponible para esta dirección o monto.',
                    };
                }

                return [
                    'clave' => $m->clave,
                    'nombre' => $m->nombre,
                    'instrucciones' => $m->config('instrucciones'),
                    'disponible' => $disponible,
                    'motivo' => $motivo,
                ];
            })
            ->values()
            ->all();
    }

    private function resolverDireccion(User $user, array $data): Direccion
    {
        if (($data['usar_direccion_existente'] ?? false) && ! empty($data['direccion_id'])) {
            return Direccion::query()
                ->where('user_id', $user->id)
                ->whereKey($data['direccion_id'])
                ->firstOrFail();
        }

        if (blank($data['calle'] ?? null)) {
            throw new DominioException('Captura la dirección de envío.');
        }

        $primera = ! Direccion::query()->where('user_id', $user->id)->exists();

        return Direccion::create([
            'user_id' => $user->id,
            'alias' => filled($data['alias'] ?? null) ? trim($data['alias']) : 'Dirección de envío',
            'nombre_receptor' => trim($data['nombre_receptor']),
            'telefono' => trim($data['telefono']),
            'calle' => trim($data['calle']),
            'numero_exterior' => trim($data['numero_exterior']),
            'numero_interior' => filled($data['numero_interior'] ?? null) ? trim($data['numero_interior']) : null,
            'colonia' => trim($data['colonia']),
            'municipio' => trim($data['municipio']),
            'estado' => trim($data['estado']),
            'pais' => 'México',
            'codigo_postal' => trim($data['codigo_postal']),
            'referencias' => filled($data['referencias'] ?? null) ? trim($data['referencias']) : null,
            'predeterminada' => $primera,
        ]);
    }
}
