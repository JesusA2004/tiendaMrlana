<?php

namespace App\Services\Pedidos;

use App\Data\DatosCheckout;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstadoPagoRegistro;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Events\Pedidos\ComprobanteRecibido;
use App\Events\Pedidos\EnvioCotizado;
use App\Events\Pedidos\EnvioElegido;
use App\Events\Pedidos\OpcionesEnvioEnviadas;
use App\Events\Pedidos\PagoAprobado;
use App\Events\Pedidos\PagoRechazado;
use App\Events\Pedidos\PedidoCancelado;
use App\Events\Pedidos\PedidoEnPreparacion;
use App\Events\Pedidos\PedidoEntregado;
use App\Events\Pedidos\PedidoEnviado;
use App\Events\Pedidos\PedidoListoParaRecoger;
use App\Events\Pedidos\PedidoRegistrado;
use App\Events\Pedidos\PedidoVencido;
use App\Exceptions\DominioException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Cupon;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\PagoComprobante;
use App\Models\Paqueteria;
use App\Models\Pedido;
use App\Models\PedidoEstatusHistorial;
use App\Models\PedidoItem;
use App\Models\PedidoOpcionEnvio;
use App\Models\ReglaEnvio;
use App\Models\User;
use App\Services\Carrito\CarritoService;
use App\Services\Cupones\CuponService;
use App\Services\Envios\EnvioService;
use App\Services\Inventario\InventarioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Máquina de estados del pedido (contrato §3). Cada transición:
 * DB::transaction + lockForUpdate del pedido, guardas (TransicionInvalidaException),
 * historial y evento de dominio despachado después del commit.
 */
class PedidoWorkflow
{
    public const HORAS_VENCIMIENTO_DEFAULT = 48;

    public const DISCO_COMPROBANTES = 'local';

    public function __construct(
        private CarritoService $carrito,
        private CuponService $cupones,
        private EnvioService $envios,
        private InventarioService $inventario,
    ) {}

    // ================================================================== creación

    public function crearDesdeCheckout(User $user, DatosCheckout $datos): Pedido
    {
        $metodo = $this->metodoDisponible($datos->metodoPago);

        if (! in_array($datos->modalidadEntrega, ModalidadEntrega::values(), true)) {
            throw new TransicionInvalidaException('Elige una modalidad de entrega válida.');
        }

        if ($datos->modalidadEntrega === ModalidadEntrega::EnvioDomicilio->value && ! $datos->direccion) {
            throw new TransicionInvalidaException('Captura la dirección de envío.');
        }

        if ($datos->direccion && $datos->direccion->user_id !== $user->id) {
            throw new AuthorizationException('La dirección no pertenece a tu cuenta.');
        }

        return DB::transaction(function () use ($user, $datos, $metodo) {
            $direccion = $datos->modalidadEntrega === ModalidadEntrega::EnvioDomicilio->value ? $datos->direccion : null;
            $calculo = $this->carrito->cotizar($datos->lineas, $datos->cuponCodigo, $user, $datos->modalidadEntrega, $direccion);

            if ($calculo['items'] === []) {
                throw new TransicionInvalidaException('Tu carrito está vacío.');
            }

            if ($calculo['cupon_error'] !== null) {
                throw new TransicionInvalidaException($calculo['cupon_error']);
            }

            if ($calculo['errores'] !== []) {
                throw new TransicionInvalidaException(implode(' ', $calculo['errores']));
            }

            $envio = $calculo['envio'];
            $resumen = $calculo['resumen'];
            $porCotizar = $envio['estado'] === EstadoEnvio::PorCotizar->value;
            $contraEntrega = $metodo->clave === MetodoPagoClave::EfectivoContraEntrega->value;

            if ($contraEntrega && ! $this->envios->permiteContraEntrega($envio, $resumen['total'], $direccion?->estado)) {
                throw new TransicionInvalidaException('El pago contra entrega no está disponible para esta dirección o monto.');
            }

            $pedido = new Pedido([
                'user_id' => $user->id,
                'direccion_id' => $direccion?->id,
                'direccion_envio' => $direccion?->copiaParaPedido(),
                'folio' => $this->generarFolio(),
                'estado_pago' => $contraEntrega ? EstadoPago::ContraEntrega->value : EstadoPago::Pendiente->value,
                'estado_logistico' => EstadoLogistico::Pendiente->value,
                'metodo_pago' => $metodo->clave,
                'modalidad_entrega' => $datos->modalidadEntrega,
                'regla_envio_id' => $envio['regla_id'],
                'envio_estado' => $envio['estado'],
                'moneda' => 'MXN',
                'subtotal' => $resumen['subtotal'],
                'descuento' => $resumen['descuento'],
                'envio' => $resumen['envio'],
                'impuesto' => 0,
                'total' => $resumen['total'],
                'saldo_pendiente' => $resumen['total'],
                'nombre_cliente' => $datos->nombreCliente,
                'correo_cliente' => $datos->correoCliente,
                'telefono_cliente' => $datos->telefonoCliente,
                'notas_cliente' => $datos->notasCliente,
                'cupon_id' => $calculo['cupon']['id'] ?? null,
                'cupon_codigo' => $calculo['cupon']['codigo'] ?? null,
                'vence_en' => (! $contraEntrega && ! $porCotizar) ? now()->addHours($this->horasVencimiento($metodo)) : null,
                'stock_reservado' => false,
            ]);
            $pedido->recalcularEstatus();
            $pedido->save();

            foreach ($calculo['items'] as $item) {
                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'producto_id' => $item['producto_id'],
                    'sku' => $item['sku'],
                    'nombre' => $item['nombre'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $this->inventario->reservarParaPedido($pedido, $user);

            if ($pedido->cupon_id) {
                $this->cupones->registrarUso(Cupon::findOrFail($pedido->cupon_id), $pedido, $user);
            }

            Pago::create([
                'pedido_id' => $pedido->id,
                'metodo_pago_id' => $metodo->id,
                'tipo' => $metodo->clave,
                'estatus' => EstadoPagoRegistro::Pendiente->value,
                'monto' => $pedido->total,
                'moneda' => 'MXN',
            ]);

            $this->historial($pedido, $user, PedidoEstatusHistorial::TIPO_GENERAL, null, match (true) {
                $porCotizar => 'Pedido registrado. Cotizaremos el envío y te avisaremos el total.',
                $contraEntrega => 'Pedido registrado con pago contra entrega.',
                default => 'Pedido registrado. Pendiente de pago.',
            }, ['metodo_pago' => $metodo->clave, 'modalidad_entrega' => $datos->modalidadEntrega]);

            PedidoRegistrado::dispatch($pedido, $user);

            return $pedido->refresh();
        });
    }

    // ================================================================== envío

    public const MAX_OPCIONES_ENVIO = 8;

    /**
     * Fija un costo de envío directo (sin opciones para el cliente). Aplica a pedidos por cotizar
     * o con opciones aún no elegidas (las opciones pendientes se descartan).
     */
    public function cotizarEnvio(Pedido $pedido, float $monto, User $user, ?string $nota = null): Pedido
    {
        Gate::forUser($user)->authorize('cotizar-envio');

        if ($monto < 0) {
            throw new TransicionInvalidaException('El costo de envío no puede ser negativo.');
        }

        return $this->transicion($pedido, function (Pedido $p) use ($monto, $user, $nota) {
            $this->exigirNoTerminal($p);

            if (! $p->envioPendiente()) {
                throw new TransicionInvalidaException('El envío de este pedido no está pendiente de cotización.');
            }

            $p->opcionesEnvio()->whereNull('seleccionada_en')->delete();
            $monto = round($monto, 2);
            $this->fijarCostoEnvio($p, $monto);
            $p->envio_cotizado_por = $user->id;

            if ($p->esContraEntrega()) {
                $regla = $p->regla_envio_id ? ReglaEnvio::find($p->regla_envio_id) : null;

                if (! $regla || ! $regla->permite_contra_entrega) {
                    // Se conserva el método, la guarda de preparación lo valida de nuevo.
                    $nota = trim(($nota ?? '').' Revisar: la regla de envío no permite contra entrega.');
                }
            }

            $anterior = $p->estatus;
            $p->recalcularEstatus();
            $p->save();

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_ENVIO, $anterior,
                'Envío cotizado: $'.number_format($monto, 2).' MXN.'.($nota ? ' '.$nota : ''), ['monto' => $monto]);

            EnvioCotizado::dispatch($p, $monto, $user);
        });
    }

    /**
     * Envía (o reemplaza) las opciones de envío para que el cliente elija. Desde `por_cotizar` u
     * `opciones_enviadas` (re-cotizar antes de que elija; motivo opcional). Tras la elección
     * (`cotizado`) sólo se re-cotiza con motivo obligatorio y mientras no haya pago en
     * revisión/aprobado ni logística iniciada (ver puedeRecotizar): se quita del total el costo elegido.
     *
     * @param  list<array<string, mixed>>  $opciones  [{paqueteria_id, servicio, costo, dias_min?, dias_max?, tiempo_texto?, notas?, recomendada?}]
     */
    public function enviarOpcionesEnvio(Pedido $pedido, User $user, array $opciones, ?string $motivo = null): Pedido
    {
        Gate::forUser($user)->authorize('cotizar-envio');
        $filas = $this->normalizarOpciones($opciones);
        $motivo = trim((string) $motivo);

        return $this->transicion($pedido, function (Pedido $p) use ($user, $filas, $motivo) {
            $this->exigirNoTerminal($p);

            if ($p->esRecogerEnTienda()) {
                throw new TransicionInvalidaException('Este pedido se recoge en tienda; no lleva envío.');
            }

            $trasEleccion = $p->envio_estado === EstadoEnvio::Cotizado->value;

            if ($trasEleccion) {
                if (! $this->puedeRecotizar($p)) {
                    throw new TransicionInvalidaException('Ya no se puede re-cotizar el envío: el pedido tiene un pago en revisión o aprobado, o ya está en proceso. Si es necesario, cancélalo.');
                }

                if (mb_strlen($motivo) < 5) {
                    throw new TransicionInvalidaException('Indica el motivo de la nueva cotización (el cliente lo verá).');
                }
            } elseif (! $p->envioPendiente()) {
                throw new TransicionInvalidaException('El envío de este pedido se calculó con una regla automática; no requiere cotización.');
            }

            $anteriores = $p->opcionesEnvio()->get();
            $reemplazo = $anteriores->isNotEmpty() || $trasEleccion;
            $p->opcionesEnvio()->delete();

            if ($trasEleccion) {
                // Se quita del total el costo elegido; el cliente volverá a elegir.
                $nuevoTotal = round((float) $p->total - (float) $p->envio, 2);
                $p->fill(['envio' => 0, 'total' => $nuevoTotal, 'saldo_pendiente' => $nuevoTotal, 'paqueteria' => null]);
                $this->actualizarMontoPagosAbiertos($p, $nuevoTotal);
            }

            $ids = [];

            foreach ($filas as $fila) {
                $ids[] = PedidoOpcionEnvio::create([...$fila, 'pedido_id' => $p->id, 'creado_por' => $user->id])->id;
            }

            $anterior = $p->estatus;
            $p->fill([
                'envio_estado' => EstadoEnvio::OpcionesEnviadas->value,
                'envio_cotizado_por' => $user->id,
                'envio_cotizado_en' => now(),
                'vence_en' => null,
            ]);
            $p->recalcularEstatus();
            $p->save();

            $cuantas = count($ids).' '.(count($ids) === 1 ? 'opción' : 'opciones');
            $comentario = $reemplazo
                ? "Actualizamos tu cotización de envío: {$cuantas} para que elijas."
                : "Tu cotización de envío está lista: {$cuantas} para que elijas.";

            if ($motivo !== '') {
                $comentario .= ' Motivo: '.mb_substr($motivo, 0, 300);
            }

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_ENVIO, $anterior, $comentario, [
                'opciones' => $filas,
                'reemplazadas' => $anteriores->map(fn (PedidoOpcionEnvio $o) => $o->presentar())->values()->all(),
                'motivo' => $motivo !== '' ? $motivo : null,
            ]);

            OpcionesEnvioEnviadas::dispatch($p, $ids, $reemplazo, $user);
        });
    }

    /**
     * El cliente (dueño) elige una de las opciones enviadas: se fija costo, paquetería, total,
     * saldo y vencimiento; el pedido pasa a pendiente de pago (contra entrega → confirmado).
     */
    public function elegirOpcionEnvio(Pedido $pedido, User $user, int $opcionId): Pedido
    {
        if ((int) $pedido->user_id !== (int) $user->id) {
            throw new AuthorizationException('Sólo el dueño del pedido puede elegir el envío.');
        }

        return $this->transicion($pedido, function (Pedido $p) use ($user, $opcionId) {
            $this->exigirNoTerminal($p);

            $opcion = PedidoOpcionEnvio::query()
                ->whereKey($opcionId)
                ->where('pedido_id', $p->id)
                ->lockForUpdate()
                ->first();

            if (! $opcion) {
                throw new TransicionInvalidaException('Esa opción de envío no corresponde a este pedido.');
            }

            if ($p->envio_estado !== EstadoEnvio::OpcionesEnviadas->value || $opcion->seleccionada_en !== null) {
                throw new TransicionInvalidaException($p->envio_estado === EstadoEnvio::Cotizado->value
                    ? 'Ya elegiste tu envío para este pedido.'
                    : 'Este pedido no tiene opciones de envío por elegir.');
            }

            $opcion->forceFill(['seleccionada_en' => now()])->save();

            $costo = round((float) $opcion->costo, 2);
            $this->fijarCostoEnvio($p, $costo);
            $p->paqueteria = mb_substr($opcion->paqueteria_nombre, 0, 120);

            $anterior = $p->estatus;
            $p->recalcularEstatus();
            $p->save();

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_ENVIO, $anterior,
                'Elegiste envío con '.$opcion->paqueteria_nombre.' ('.$opcion->servicio.'): $'.number_format($costo, 2).' MXN.',
                ['opcion_id' => $opcion->id, 'costo' => $costo, 'paqueteria' => $opcion->paqueteria_nombre, 'servicio' => $opcion->servicio]);

            EnvioElegido::dispatch($p, $opcion, $user);
        });
    }

    /** Re-cotizar tras la elección: sin pago en revisión/aprobado y sin logística iniciada. */
    public function puedeRecotizar(Pedido $p): bool
    {
        return ! $p->esTerminal()
            && ! $p->esRecogerEnTienda()
            && $p->envio_estado === EstadoEnvio::Cotizado->value
            && $p->estado_logistico === EstadoLogistico::Pendiente->value
            && in_array($p->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value, EstadoPago::ContraEntrega->value], true);
    }

    // ================================================================== pagos

    public function registrarComprobante(
        Pedido $pedido,
        User $user,
        UploadedFile $archivo,
        ?string $referencia = null,
        ?string $fecha = null,
        ?float $monto = null,
    ): PagoComprobante {
        if ($pedido->user_id !== $user->id) {
            throw new AuthorizationException('Sólo el dueño del pedido puede subir comprobantes.');
        }

        $this->validarArchivo($archivo);

        $extension = strtolower($archivo->guessExtension() ?: $archivo->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $ruta = 'comprobantes/'.$pedido->id.'/'.Str::uuid().'.'.$extension;
        $contenido = file_get_contents($archivo->getRealPath());

        Storage::disk(self::DISCO_COMPROBANTES)->put($ruta, $contenido);

        try {
            $comprobante = null;

            $this->transicion($pedido, function (Pedido $p) use ($user, $archivo, $ruta, $contenido, $referencia, $fecha, $monto, &$comprobante) {
                $this->exigirNoTerminal($p);

                if ($p->metodo_pago !== MetodoPagoClave::Transferencia->value) {
                    throw new TransicionInvalidaException('Este pedido no se paga por transferencia.');
                }

                if ($p->envioPendiente()) {
                    throw new TransicionInvalidaException($p->esperaEleccionEnvio()
                        ? 'Primero elige tu opción de envío para conocer el total antes de pagar.'
                        : 'Espera a que coticemos el envío para conocer el total antes de pagar.');
                }

                if (! in_array($p->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value], true)) {
                    throw new TransicionInvalidaException('Este pedido ya tiene un pago en revisión o aprobado.');
                }

                $pago = $p->pagos()->latest('id')->lockForUpdate()->first();

                if (! $pago || ! in_array($pago->estatus, [EstadoPagoRegistro::Pendiente->value, EstadoPagoRegistro::Rechazado->value], true)) {
                    throw new TransicionInvalidaException('No hay un pago pendiente para este pedido.');
                }

                $pago->fill([
                    'estatus' => EstadoPagoRegistro::EnRevision->value,
                    'referencia_cliente' => $referencia,
                    'motivo_rechazo' => null,
                ])->save();

                $comprobante = PagoComprobante::create([
                    'pago_id' => $pago->id,
                    'user_id' => $user->id,
                    'disk' => self::DISCO_COMPROBANTES,
                    'ruta' => $ruta,
                    'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                    'mime' => (string) $archivo->getMimeType(),
                    'tamano' => (int) $archivo->getSize(),
                    'sha256' => hash('sha256', $contenido),
                    'referencia' => $referencia,
                    'fecha_transferencia' => $fecha,
                    'monto_declarado' => $monto,
                    'estado' => PagoComprobante::ESTADO_RECIBIDO,
                ]);

                $anterior = $p->estado_pago;
                $p->estado_pago = EstadoPago::EnRevision->value;
                $p->recalcularEstatus();
                $p->save();

                $this->historial($p, $user, PedidoEstatusHistorial::TIPO_PAGO, $anterior, 'Comprobante de pago recibido. Lo revisaremos a la brevedad.');

                ComprobanteRecibido::dispatch($p, $pago, $comprobante, $user);
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISCO_COMPROBANTES)->delete($ruta);

            throw $e;
        }

        return $comprobante;
    }

    public function aprobarPago(Pago $pago, User $user, ?string $notas = null): Pedido
    {
        Gate::forUser($user)->authorize('revisar-pagos');

        return $this->transicion($pago->pedido, function (Pedido $p) use ($pago, $user, $notas) {
            $pago = $this->bloquearPago($pago, $p);

            if ($pago->estatus === EstadoPagoRegistro::Aprobado->value || $p->estado_pago === EstadoPago::Aprobado->value) {
                throw new TransicionInvalidaException('El pago ya fue aprobado.');
            }

            $this->exigirNoTerminal($p);

            if ($p->envioPendiente()) {
                throw new TransicionInvalidaException($this->mensajeEnvioPendiente($p));
            }

            $permitido = match ($pago->tipo) {
                MetodoPagoClave::Transferencia->value => $pago->estatus === EstadoPagoRegistro::EnRevision->value,
                MetodoPagoClave::EfectivoPresencial->value => $pago->estatus === EstadoPagoRegistro::Pendiente->value,
                default => false,
            };

            if (! $permitido) {
                throw new TransicionInvalidaException(match ($pago->tipo) {
                    MetodoPagoClave::Transferencia->value => 'Sólo se puede aprobar una transferencia con comprobante en revisión.',
                    MetodoPagoClave::EfectivoContraEntrega->value => 'Los pagos contra entrega se confirman con «confirmar efectivo».',
                    default => 'Este pago no puede aprobarse manualmente.',
                });
            }

            $this->marcarPagoAprobado($p, $pago, $user, $notas, null);
            $pago->comprobantes()->where('estado', PagoComprobante::ESTADO_RECIBIDO)->update(['estado' => PagoComprobante::ESTADO_APROBADO]);

            PagoAprobado::dispatch($p, $pago, $user);
        });
    }

    public function rechazarPago(Pago $pago, User $user, string $motivo): Pedido
    {
        Gate::forUser($user)->authorize('revisar-pagos');
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new TransicionInvalidaException('Indica el motivo del rechazo.');
        }

        return $this->transicion($pago->pedido, function (Pedido $p) use ($pago, $user, $motivo) {
            $pago = $this->bloquearPago($pago, $p);

            if ($pago->estatus === EstadoPagoRegistro::Aprobado->value) {
                throw new TransicionInvalidaException('El pago ya fue aprobado; no puede rechazarse.');
            }

            if ($pago->estatus !== EstadoPagoRegistro::EnRevision->value) {
                throw new TransicionInvalidaException('Sólo se puede rechazar un pago en revisión.');
            }

            $pago->fill([
                'estatus' => EstadoPagoRegistro::Rechazado->value,
                'motivo_rechazo' => mb_substr($motivo, 0, 500),
                'revisado_por' => $user->id,
                'revisado_en' => now(),
            ])->save();
            $pago->comprobantes()->where('estado', PagoComprobante::ESTADO_RECIBIDO)->update(['estado' => PagoComprobante::ESTADO_RECHAZADO]);

            $anterior = $p->estado_pago;
            $p->estado_pago = EstadoPago::Rechazado->value;
            $p->recalcularEstatus();
            $p->save();

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_PAGO, $anterior, 'Comprobante rechazado: '.$motivo, ['motivo' => $motivo]);

            PagoRechazado::dispatch($p, $pago, $motivo, $user);
        });
    }

    public function confirmarEfectivo(Pago $pago, User $user, float $montoRecibido, ?string $notas = null): Pedido
    {
        Gate::forUser($user)->authorize('revisar-pagos');

        return $this->transicion($pago->pedido, function (Pedido $p) use ($pago, $user, $montoRecibido, $notas) {
            $this->confirmarEfectivoBloqueado($p, $pago, $user, $montoRecibido, $notas);
        });
    }

    public function registrarReembolso(Pedido $pedido, User $user, ?string $nota = null): Pedido
    {
        Gate::forUser($user)->authorize('revisar-pagos');

        return $this->transicion($pedido, function (Pedido $p) use ($user, $nota) {
            if ($p->estado_pago !== EstadoPago::Aprobado->value) {
                throw new TransicionInvalidaException('Sólo se puede reembolsar un pedido con pago aprobado.');
            }

            if ($p->stock_reservado && ! $p->enviado_en) {
                $this->inventario->liberarDePedido($p, $user, 'Reembolso del pedido '.$p->folio);
            }

            $p->pagos()->where('estatus', EstadoPagoRegistro::Aprobado->value)->update(['estatus' => EstadoPagoRegistro::Reembolsado->value]);

            $anterior = $p->estatus;
            $p->estado_pago = EstadoPago::Reembolsado->value;
            $p->estatus = EstatusPedido::Reembolsado->value;
            $p->save();

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_PAGO, $anterior, 'Reembolso registrado.'.($nota ? ' '.$nota : ''));
        });
    }

    // ================================================================== logística

    public function iniciarPreparacion(Pedido $pedido, User $user): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');

        return $this->transicion($pedido, function (Pedido $p) use ($user) {
            $this->exigirNoTerminal($p);
            $this->exigirLogistico($p, [EstadoLogistico::Pendiente->value], 'El pedido ya está en preparación o fue enviado.');

            if ($p->envioPendiente()) {
                throw new TransicionInvalidaException($this->mensajeEnvioPendiente($p));
            }

            if (! $this->pagoPermitePreparar($p)) {
                throw new TransicionInvalidaException('No se puede preparar: el pago aún no está aprobado.');
            }

            $this->cambiarLogistico($p, $user, EstadoLogistico::EnPreparacion, ['preparando_en' => now()], 'Pedido en preparación.');

            PedidoEnPreparacion::dispatch($p, $user);
        });
    }

    public function marcarListoEnvio(Pedido $pedido, User $user): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');

        return $this->transicion($pedido, function (Pedido $p) use ($user) {
            $this->exigirNoTerminal($p);

            if ($p->esRecogerEnTienda()) {
                throw new TransicionInvalidaException('Este pedido se recoge en tienda; usa «listo para recoger».');
            }

            $this->exigirLogistico($p, [EstadoLogistico::EnPreparacion->value], 'El pedido debe estar en preparación.');
            $this->cambiarLogistico($p, $user, EstadoLogistico::ListoEnvio, ['listo_envio_en' => now()], 'Pedido listo para envío.');
        });
    }

    public function marcarListoRecoger(Pedido $pedido, User $user): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');

        return $this->transicion($pedido, function (Pedido $p) use ($user) {
            $this->exigirNoTerminal($p);

            if (! $p->esRecogerEnTienda()) {
                throw new TransicionInvalidaException('Este pedido es con envío a domicilio.');
            }

            $this->exigirLogistico($p, [EstadoLogistico::EnPreparacion->value], 'El pedido debe estar en preparación.');
            $this->cambiarLogistico($p, $user, EstadoLogistico::ListoRecoger, ['listo_envio_en' => now()], 'Pedido listo para recoger en tienda.');

            PedidoListoParaRecoger::dispatch($p, $user);
        });
    }

    public function registrarEnvio(Pedido $pedido, User $user, string $paqueteria, string $guia, ?string $fechaEnvio = null): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');
        $paqueteria = trim($paqueteria);
        $guia = trim($guia);

        if ($paqueteria === '' || $guia === '') {
            throw new TransicionInvalidaException('Captura la paquetería y el número de guía.');
        }

        return $this->transicion($pedido, function (Pedido $p) use ($user, $paqueteria, $guia, $fechaEnvio) {
            $this->exigirNoTerminal($p);

            if ($p->esRecogerEnTienda()) {
                throw new TransicionInvalidaException('Este pedido se recoge en tienda; no lleva guía.');
            }

            $this->exigirLogistico($p, [
                EstadoLogistico::EnPreparacion->value,
                EstadoLogistico::ListoEnvio->value,
                EstadoLogistico::Incidencia->value,
            ], 'El pedido debe estar en preparación o listo para envío.');

            $this->cambiarLogistico($p, $user, EstadoLogistico::Enviado, [
                'paqueteria' => mb_substr($paqueteria, 0, 120),
                'numero_guia' => mb_substr($guia, 0, 180),
                'fecha_envio' => $fechaEnvio ?: now()->toDateString(),
                'enviado_en' => now(),
            ], "Pedido enviado por {$paqueteria}. Guía: {$guia}.", ['paqueteria' => $paqueteria, 'guia' => $guia]);

            PedidoEnviado::dispatch($p, $user);
        });
    }

    /**
     * Para contra entrega debe confirmarse el efectivo antes o en esta misma acción ($montoRecibido).
     */
    public function confirmarEntrega(Pedido $pedido, User $user, ?float $montoRecibido = null, ?string $notas = null): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');

        return $this->transicion($pedido, function (Pedido $p) use ($user, $montoRecibido, $notas) {
            $this->exigirNoTerminal($p);

            $origen = $p->esRecogerEnTienda()
                ? [EstadoLogistico::ListoRecoger->value, EstadoLogistico::EnPreparacion->value, EstadoLogistico::Incidencia->value]
                : [EstadoLogistico::Enviado->value, EstadoLogistico::Incidencia->value];

            $this->exigirLogistico($p, $origen, $p->esRecogerEnTienda()
                ? 'El pedido debe estar listo para recoger.'
                : 'El pedido debe estar enviado para confirmar la entrega.');

            if ($p->estado_pago === EstadoPago::ContraEntrega->value) {
                if ($montoRecibido === null) {
                    throw new TransicionInvalidaException('Confirma el efectivo recibido antes de marcar la entrega.');
                }

                Gate::forUser($user)->authorize('revisar-pagos');
                $pago = $p->pagos()->latest('id')->firstOrFail();
                $this->confirmarEfectivoBloqueado($p, $pago, $user, $montoRecibido, $notas);
            } elseif ($p->estado_pago !== EstadoPago::Aprobado->value) {
                throw new TransicionInvalidaException('No se puede entregar un pedido sin pago aprobado.');
            }

            $this->cambiarLogistico($p, $user, EstadoLogistico::Entregado, ['entregado_en' => now()], 'Pedido entregado.');

            PedidoEntregado::dispatch($p, $user);
        });
    }

    public function registrarIncidencia(Pedido $pedido, User $user, string $nota): Pedido
    {
        Gate::forUser($user)->authorize('operar-pedidos');
        $nota = trim($nota);

        if ($nota === '') {
            throw new TransicionInvalidaException('Describe la incidencia.');
        }

        return $this->transicion($pedido, function (Pedido $p) use ($user, $nota) {
            $this->exigirNoTerminal($p);
            $this->exigirLogistico($p, [
                EstadoLogistico::EnPreparacion->value,
                EstadoLogistico::ListoEnvio->value,
                EstadoLogistico::ListoRecoger->value,
                EstadoLogistico::Enviado->value,
            ], 'Sólo se registran incidencias en pedidos en proceso.');

            $this->cambiarLogistico($p, $user, EstadoLogistico::Incidencia, [], 'Incidencia: '.$nota, ['nota' => $nota], false);
        });
    }

    // ================================================================== cierre

    public function cancelar(Pedido $pedido, User $user, string $motivo): Pedido
    {
        Gate::forUser($user)->authorize('cancelar-pedidos');
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new TransicionInvalidaException('Indica el motivo de la cancelación.');
        }

        return $this->transicion($pedido, function (Pedido $p) use ($user, $motivo) {
            $this->exigirNoTerminal($p);

            if ($p->enviado_en || in_array($p->estado_logistico, [EstadoLogistico::Enviado->value, EstadoLogistico::Entregado->value], true)) {
                throw new TransicionInvalidaException('No se puede cancelar un pedido que ya fue enviado o entregado.');
            }

            $this->inventario->liberarDePedido($p, $user, 'Cancelación del pedido '.$p->folio);
            $this->cupones->liberarUso($p);

            $requiereReembolso = $p->estado_pago === EstadoPago::Aprobado->value;

            $p->pagos()
                ->whereIn('estatus', [EstadoPagoRegistro::Pendiente->value, EstadoPagoRegistro::EnRevision->value, EstadoPagoRegistro::Rechazado->value])
                ->update(['estatus' => EstadoPagoRegistro::Cancelado->value]);

            $anterior = $p->estatus;
            $p->fill([
                'estatus' => EstatusPedido::Cancelado->value,
                'cancelado_en' => now(),
                'cancelado_por' => $user->id,
                'motivo_cancelacion' => mb_substr($motivo, 0, 500),
                'vence_en' => null,
            ])->save();

            $this->historial($p, $user, PedidoEstatusHistorial::TIPO_GENERAL, $anterior, 'Pedido cancelado: '.$motivo, ['motivo' => $motivo]);

            if ($requiereReembolso) {
                $this->historial($p, $user, PedidoEstatusHistorial::TIPO_PAGO, $p->estado_pago,
                    'Reembolso manual pendiente: el pedido tenía un pago aprobado.', ['reembolso_pendiente' => true], false);
            }

            PedidoCancelado::dispatch($p, $motivo, $requiereReembolso, $user);
        });
    }

    /**
     * Vence un pedido sin pago (comando pedidos:vencer). Devuelve false si ya no aplica.
     */
    public function vencer(Pedido $pedido): bool
    {
        $vencido = false;

        $this->transicion($pedido, function (Pedido $p) use (&$vencido) {
            $aplica = $p->estatus === EstatusPedido::PendientePago->value
                && in_array($p->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value], true)
                && $p->vence_en !== null
                && $p->vence_en->isPast()
                && ! $p->comprobantes()->where('pago_comprobantes.estado', PagoComprobante::ESTADO_RECIBIDO)->exists();

            if (! $aplica) {
                return;
            }

            $this->inventario->liberarDePedido($p, null, 'Vencimiento del pedido '.$p->folio);
            $this->cupones->liberarUso($p);
            $p->pagos()->whereIn('estatus', [EstadoPagoRegistro::Pendiente->value, EstadoPagoRegistro::Rechazado->value])
                ->update(['estatus' => EstadoPagoRegistro::Cancelado->value]);

            $anterior = $p->estatus;
            $p->fill(['estatus' => EstatusPedido::Vencido->value, 'vencido_en' => now()])->save();

            $this->historial($p, null, PedidoEstatusHistorial::TIPO_SISTEMA, $anterior, 'El pedido venció por falta de pago.');

            PedidoVencido::dispatch($p);
            $vencido = true;
        });

        return $vencido;
    }

    public function agregarNota(Pedido $pedido, User $user, string $nota, bool $visibleCliente = false): PedidoEstatusHistorial
    {
        Gate::forUser($user)->authorize('operar-pedidos');
        $nota = trim($nota);

        if ($nota === '') {
            throw new TransicionInvalidaException('La nota está vacía.');
        }

        return $this->historial($pedido, $user, PedidoEstatusHistorial::TIPO_NOTA, null, $nota, [], $visibleCliente);
    }

    // ================================================================== consultas

    /** Acciones disponibles para la UI (no sustituye las guardas). */
    public function accionesDisponibles(Pedido $pedido, User $user): array
    {
        $g = Gate::forUser($user);
        $terminal = $pedido->esTerminal();
        $log = $pedido->estado_logistico;

        return [
            'cotizar_envio' => $g->allows('cotizar-envio') && ! $terminal && ! $pedido->esRecogerEnTienda() && $pedido->envioPendiente(),
            'recotizar_envio' => $g->allows('cotizar-envio') && $this->puedeRecotizar($pedido),
            'aprobar_pago' => $g->allows('revisar-pagos') && ! $terminal && (
                ($pedido->metodo_pago === MetodoPagoClave::Transferencia->value && $pedido->estado_pago === EstadoPago::EnRevision->value)
                || ($pedido->metodo_pago === MetodoPagoClave::EfectivoPresencial->value && $pedido->estado_pago === EstadoPago::Pendiente->value)),
            'rechazar_pago' => $g->allows('revisar-pagos') && ! $terminal && $pedido->estado_pago === EstadoPago::EnRevision->value,
            'confirmar_efectivo' => $g->allows('revisar-pagos') && ! $terminal
                && in_array($pedido->metodo_pago, [MetodoPagoClave::EfectivoPresencial->value, MetodoPagoClave::EfectivoContraEntrega->value], true)
                && in_array($pedido->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::ContraEntrega->value], true),
            'iniciar_preparacion' => $g->allows('operar-pedidos') && ! $terminal && $log === EstadoLogistico::Pendiente->value
                && ! $pedido->envioPendiente() && $this->pagoPermitePreparar($pedido),
            'marcar_listo_envio' => $g->allows('operar-pedidos') && ! $terminal && ! $pedido->esRecogerEnTienda() && $log === EstadoLogistico::EnPreparacion->value,
            'marcar_listo_recoger' => $g->allows('operar-pedidos') && ! $terminal && $pedido->esRecogerEnTienda() && $log === EstadoLogistico::EnPreparacion->value,
            'registrar_envio' => $g->allows('operar-pedidos') && ! $terminal && ! $pedido->esRecogerEnTienda()
                && in_array($log, [EstadoLogistico::EnPreparacion->value, EstadoLogistico::ListoEnvio->value, EstadoLogistico::Incidencia->value], true),
            'confirmar_entrega' => $g->allows('operar-pedidos') && ! $terminal && (
                $pedido->esRecogerEnTienda()
                    ? in_array($log, [EstadoLogistico::ListoRecoger->value, EstadoLogistico::EnPreparacion->value, EstadoLogistico::Incidencia->value], true)
                    : in_array($log, [EstadoLogistico::Enviado->value, EstadoLogistico::Incidencia->value], true)),
            'registrar_incidencia' => $g->allows('operar-pedidos') && ! $terminal
                && in_array($log, [EstadoLogistico::EnPreparacion->value, EstadoLogistico::ListoEnvio->value, EstadoLogistico::ListoRecoger->value, EstadoLogistico::Enviado->value], true),
            'cancelar' => $g->allows('cancelar-pedidos') && ! $terminal && ! $pedido->enviado_en
                && ! in_array($log, [EstadoLogistico::Enviado->value, EstadoLogistico::Entregado->value], true),
            'reembolsar' => $g->allows('revisar-pagos') && $pedido->estado_pago === EstadoPago::Aprobado->value,
            'agregar_nota' => $g->allows('operar-pedidos'),
        ];
    }

    // ================================================================== internos

    /**
     * @param  callable(Pedido): void  $callback
     */
    private function transicion(Pedido $pedido, callable $callback): Pedido
    {
        $bloqueado = DB::transaction(function () use ($pedido, $callback) {
            $p = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();
            $callback($p);

            return $p;
        });

        $pedido->setRawAttributes($bloqueado->getAttributes(), true);
        $pedido->unsetRelations();

        return $bloqueado;
    }

    private function bloquearPago(Pago $pago, Pedido $pedido): Pago
    {
        $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

        if ((int) $bloqueado->pedido_id !== (int) $pedido->id) {
            throw new TransicionInvalidaException('El pago no corresponde al pedido.');
        }

        return $bloqueado;
    }

    private function confirmarEfectivoBloqueado(Pedido $p, Pago $pago, User $user, float $montoRecibido, ?string $notas): void
    {
        $pago = $this->bloquearPago($pago, $p);

        if ($pago->estatus === EstadoPagoRegistro::Aprobado->value || $p->estado_pago === EstadoPago::Aprobado->value) {
            throw new TransicionInvalidaException('El pago ya fue aprobado.');
        }

        $this->exigirNoTerminal($p);

        if (! in_array($pago->tipo, [MetodoPagoClave::EfectivoPresencial->value, MetodoPagoClave::EfectivoContraEntrega->value], true)) {
            throw new TransicionInvalidaException('Este pago no es en efectivo.');
        }

        if ($pago->estatus !== EstadoPagoRegistro::Pendiente->value) {
            throw new TransicionInvalidaException('El pago no está pendiente.');
        }

        if ($p->envioPendiente()) {
            throw new TransicionInvalidaException($this->mensajeEnvioPendiente($p));
        }

        $saldo = round((float) $p->saldo_pendiente, 2);

        if (round($montoRecibido, 2) < $saldo) {
            throw new TransicionInvalidaException('El monto recibido ($'.number_format($montoRecibido, 2).') es menor al saldo pendiente ($'.number_format($saldo, 2).').');
        }

        $this->marcarPagoAprobado($p, $pago, $user, $notas, round($montoRecibido, 2));

        PagoAprobado::dispatch($p, $pago, $user);
    }

    private function marcarPagoAprobado(Pedido $p, Pago $pago, User $user, ?string $notas, ?float $montoRecibido): void
    {
        $pago->fill([
            'estatus' => EstadoPagoRegistro::Aprobado->value,
            'revisado_por' => $user->id,
            'revisado_en' => now(),
            'pagado_en' => now(),
            'notas_internas' => $notas,
            'monto' => $p->total,
        ]);

        if ($montoRecibido !== null) {
            $pago->fill(['monto_recibido' => $montoRecibido, 'recibido_por' => $user->id, 'recibido_en' => now()]);
        }

        $pago->save();

        $anterior = $p->estado_pago;
        $p->fill([
            'estado_pago' => EstadoPago::Aprobado->value,
            'pagado_en' => now(),
            'saldo_pendiente' => 0,
            'vence_en' => null,
        ]);
        $p->recalcularEstatus();
        $p->save();

        $this->historial($p, $user, PedidoEstatusHistorial::TIPO_PAGO, $anterior, $montoRecibido !== null
            ? 'Pago en efectivo confirmado.'
            : 'Pago aprobado.', $montoRecibido !== null ? ['monto_recibido' => $montoRecibido] : []);
    }

    private function cambiarLogistico(Pedido $p, User $user, EstadoLogistico $nuevo, array $extra, string $comentario, array $datos = [], bool $visible = true): void
    {
        $anterior = $p->estado_logistico;
        $p->fill($extra);
        $p->estado_logistico = $nuevo->value;
        $p->recalcularEstatus();
        $p->save();

        $this->historial($p, $user, PedidoEstatusHistorial::TIPO_LOGISTICO, $anterior, $comentario, $datos, $visible);
    }

    private function pagoPermitePreparar(Pedido $p): bool
    {
        if ($p->estado_pago === EstadoPago::Aprobado->value) {
            return true;
        }

        if ($p->esContraEntrega() && $p->estado_pago === EstadoPago::ContraEntrega->value) {
            $metodo = MetodoPago::query()->where('clave', MetodoPagoClave::EfectivoContraEntrega->value)->first();
            $regla = $p->regla_envio_id ? ReglaEnvio::find($p->regla_envio_id) : null;

            return (bool) $metodo?->activo && (bool) $regla?->permite_contra_entrega;
        }

        return false;
    }

    /**
     * Fija el costo final del envío (sin guardar): total, saldo, estado `cotizado` y vencimiento
     * del pago (transferencia/efectivo presencial). Contra entrega no vence.
     */
    private function fijarCostoEnvio(Pedido $p, float $monto): void
    {
        $nuevoTotal = round((float) $p->total - (float) $p->envio + $monto, 2);
        $metodo = MetodoPago::query()->where('clave', $p->metodo_pago)->first();

        $p->fill([
            'envio' => $monto,
            'total' => $nuevoTotal,
            'saldo_pendiente' => $p->estado_pago === EstadoPago::Aprobado->value ? 0 : $nuevoTotal,
            'envio_estado' => EstadoEnvio::Cotizado->value,
            'envio_cotizado_en' => now(),
            'vence_en' => in_array($p->metodo_pago, [MetodoPagoClave::Transferencia->value, MetodoPagoClave::EfectivoPresencial->value], true)
                ? now()->addHours($this->horasVencimiento($metodo))
                : null,
        ]);

        $this->actualizarMontoPagosAbiertos($p, $nuevoTotal);
    }

    private function actualizarMontoPagosAbiertos(Pedido $p, float $total): void
    {
        $p->pagos()
            ->whereIn('estatus', [EstadoPagoRegistro::Pendiente->value, EstadoPagoRegistro::Rechazado->value])
            ->update(['monto' => $total]);
    }

    /**
     * Valida y normaliza las opciones de envío (defensa en profundidad; el controlador ya valida).
     *
     * @param  list<array<string, mixed>>  $opciones
     * @return list<array<string, mixed>>
     */
    private function normalizarOpciones(array $opciones): array
    {
        if ($opciones === []) {
            throw new TransicionInvalidaException('Agrega al menos una opción de envío.');
        }

        if (count($opciones) > self::MAX_OPCIONES_ENVIO) {
            throw new TransicionInvalidaException('Envía como máximo '.self::MAX_OPCIONES_ENVIO.' opciones de envío.');
        }

        $paqueterias = Paqueteria::query()
            ->whereIn('id', collect($opciones)->pluck('paqueteria_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        $recomendadaUsada = false;
        $filas = [];

        foreach (array_values($opciones) as $i => $o) {
            $n = $i + 1;
            $paqueteria = $paqueterias->get((int) ($o['paqueteria_id'] ?? 0));
            $servicio = trim((string) ($o['servicio'] ?? ''));
            $costo = $o['costo'] ?? null;
            $min = isset($o['dias_min']) && $o['dias_min'] !== '' ? (int) $o['dias_min'] : null;
            $max = isset($o['dias_max']) && $o['dias_max'] !== '' ? (int) $o['dias_max'] : null;

            if (! $paqueteria) {
                throw new TransicionInvalidaException("Opción {$n}: elige una paquetería activa del catálogo.");
            }

            if ($servicio === '') {
                throw new TransicionInvalidaException("Opción {$n}: indica el servicio (p. ej. Estándar o Express).");
            }

            if (! is_numeric($costo) || (float) $costo < 0 || (float) $costo > 999999.99) {
                throw new TransicionInvalidaException("Opción {$n}: el costo debe ser un monto válido.");
            }

            if (($min !== null && ($min < 0 || $min > 365)) || ($max !== null && ($max < 0 || $max > 365)) || ($min !== null && $max !== null && $min > $max)) {
                throw new TransicionInvalidaException("Opción {$n}: revisa los días estimados (mínimo ≤ máximo).");
            }

            $recomendada = (bool) ($o['recomendada'] ?? false) && ! $recomendadaUsada;
            $recomendadaUsada = $recomendadaUsada || $recomendada;
            $tiempo = trim((string) ($o['tiempo_texto'] ?? ''));
            $notas = trim((string) ($o['notas'] ?? ''));

            $filas[] = [
                'paqueteria_id' => $paqueteria->id,
                'paqueteria_nombre' => mb_substr($paqueteria->nombre, 0, 80),
                'servicio' => mb_substr($servicio, 0, 120),
                'costo' => round((float) $costo, 2),
                'dias_min' => $min,
                'dias_max' => $max,
                'tiempo_texto' => $tiempo !== '' ? mb_substr($tiempo, 0, 120) : null,
                'notas' => $notas !== '' ? mb_substr($notas, 0, 500) : null,
                'recomendada' => $recomendada,
            ];
        }

        return $filas;
    }

    private function mensajeEnvioPendiente(Pedido $p): string
    {
        return $p->esperaEleccionEnvio()
            ? 'El cliente aún no elige su opción de envío.'
            : 'Primero cotiza el envío del pedido.';
    }

    private function exigirNoTerminal(Pedido $p): void
    {
        if ($p->esTerminal()) {
            throw new TransicionInvalidaException('El pedido está '.(EstatusPedido::labelDe($p->estatus) ?? $p->estatus).' y ya no admite cambios.');
        }
    }

    /** @param  list<string>  $permitidos */
    private function exigirLogistico(Pedido $p, array $permitidos, string $mensaje): void
    {
        if (! in_array($p->estado_logistico, $permitidos, true)) {
            throw new TransicionInvalidaException($mensaje);
        }
    }

    private function historial(Pedido $p, ?User $user, string $tipo, ?string $anterior, string $comentario, array $datos = [], bool $visible = true): PedidoEstatusHistorial
    {
        return PedidoEstatusHistorial::create([
            'pedido_id' => $p->id,
            'user_id' => $user?->id,
            'tipo' => $tipo,
            'estado_anterior' => $anterior,
            'estatus' => $p->estatus,
            'comentario' => $comentario,
            'datos' => $datos ?: null,
            'visible_cliente' => $visible,
        ]);
    }

    private function metodoDisponible(string $clave): MetodoPago
    {
        $permitidos = [
            MetodoPagoClave::Transferencia->value,
            MetodoPagoClave::EfectivoPresencial->value,
            MetodoPagoClave::EfectivoContraEntrega->value,
        ];

        if (! in_array($clave, $permitidos, true)) {
            throw new TransicionInvalidaException('El método de pago no está disponible.');
        }

        $metodo = MetodoPago::query()->where('clave', $clave)->where('activo', true)->first();

        if (! $metodo) {
            throw new TransicionInvalidaException('El método de pago no está disponible.');
        }

        return $metodo;
    }

    private function horasVencimiento(?MetodoPago $metodo): int
    {
        $horas = (int) (($metodo?->configuracion ?? [])['horas_vencimiento'] ?? self::HORAS_VENCIMIENTO_DEFAULT);

        return $horas > 0 ? $horas : self::HORAS_VENCIMIENTO_DEFAULT;
    }

    private function validarArchivo(UploadedFile $archivo): void
    {
        if (! $archivo->isValid()) {
            throw new DominioException('No se pudo recibir el archivo. Intenta de nuevo.');
        }

        if ($archivo->getSize() > PagoComprobante::MAX_KB * 1024) {
            throw new DominioException('El comprobante no debe pesar más de 5 MB.');
        }

        $extension = strtolower($archivo->getClientOriginalExtension());
        $mime = (string) $archivo->getMimeType(); // mime real (finfo)

        if (! in_array($extension, PagoComprobante::EXTENSIONES, true) || ! in_array($mime, PagoComprobante::MIMES, true)) {
            throw new DominioException('El comprobante debe ser una imagen (JPG, PNG, WEBP) o un PDF.');
        }
    }

    private function generarFolio(): string
    {
        do {
            $folio = 'ML-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Pedido::query()->where('folio', $folio)->exists());

        return $folio;
    }
}
