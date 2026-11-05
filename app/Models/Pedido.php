<?php

namespace App\Models;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Mail\Pedidos\PedidoEnviadoMail;
use Database\Factories\PedidoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Las columnas de estado se guardan como string (no se castean a enum) para conservar
 * valores históricos; usar App\Enums\* para comparar/etiquetar.
 */
class Pedido extends Model
{
    /** @use HasFactory<PedidoFactory> */
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'user_id',
        'direccion_id',
        'direccion_envio',
        'folio',
        'estatus',
        'estado_pago',
        'estado_logistico',
        'metodo_pago',
        'modalidad_entrega',
        'moneda',
        'subtotal',
        'descuento',
        'envio',
        'impuesto',
        'total',
        'saldo_pendiente',
        'nombre_cliente',
        'correo_cliente',
        'telefono_cliente',
        'notas_cliente',
        'paqueteria',
        'numero_guia',
        'fecha_envio',
        'preparando_en',
        'listo_envio_en',
        'enviado_en',
        'entregado_en',
        'comentario_interno',
        'pagado_en',
        'cancelado_en',
        'cancelado_por',
        'motivo_cancelacion',
        'vence_en',
        'vencido_en',
        'regla_envio_id',
        'envio_estado',
        'envio_cotizado_por',
        'envio_cotizado_en',
        'cupon_id',
        'cupon_codigo',
        'stock_reservado',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'envio' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'preparando_en' => 'datetime',
        'listo_envio_en' => 'datetime',
        'enviado_en' => 'datetime',
        'entregado_en' => 'datetime',
        'pagado_en' => 'datetime',
        'cancelado_en' => 'datetime',
        'vence_en' => 'datetime',
        'vencido_en' => 'datetime',
        'envio_cotizado_en' => 'datetime',
        'fecha_envio' => 'date',
        'stock_reservado' => 'boolean',
        'direccion_envio' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class, 'direccion_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PedidoItem::class, 'pedido_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'pedido_id');
    }

    /** Pago vigente (el más reciente). */
    public function pagoActual(): HasOne
    {
        return $this->hasOne(Pago::class, 'pedido_id')->latestOfMany();
    }

    public function comprobantes(): HasManyThrough
    {
        return $this->hasManyThrough(PagoComprobante::class, Pago::class, 'pedido_id', 'pago_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(PedidoEstatusHistorial::class, 'pedido_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'pedido_id');
    }

    public function reglaEnvio(): BelongsTo
    {
        return $this->belongsTo(ReglaEnvio::class, 'regla_envio_id');
    }

    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class, 'cupon_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function envioCotizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'envio_cotizado_por');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionPedido::class, 'pedido_id');
    }

    public function opcionesEnvio(): HasMany
    {
        return $this->hasMany(PedidoOpcionEnvio::class, 'pedido_id');
    }

    /** Opción de envío que eligió el cliente (si la hay). */
    public function opcionEnvioElegida(): HasOne
    {
        return $this->hasOne(PedidoOpcionEnvio::class, 'pedido_id')->whereNotNull('seleccionada_en');
    }

    /** El costo de envío aún no es final (por cotizar o esperando que el cliente elija). */
    public function envioPendiente(): bool
    {
        return in_array($this->envio_estado, EstadoEnvio::pendientes(), true);
    }

    public function esperaEleccionEnvio(): bool
    {
        return $this->envio_estado === EstadoEnvio::OpcionesEnviadas->value;
    }

    /**
     * Dirección de entrega del pedido: la copia guardada al comprar (`direccion_envio`);
     * si no existe (pedidos muy viejos sin relleno), la dirección vinculada.
     *
     * @return array<string, mixed>|null
     */
    public function direccionEntrega(): ?array
    {
        $copia = $this->direccion_envio;

        if (is_array($copia) && $copia !== []) {
            return $copia;
        }

        if (! $this->direccion_id) {
            return null;
        }

        return $this->direccion?->copiaParaPedido();
    }

    /** Enlace para rastrear la guía con la paquetería del catálogo (si tiene URL con {guia}). */
    public function urlRastreo(): ?string
    {
        if (blank($this->numero_guia) || blank($this->paqueteria)) {
            return null;
        }

        $nombre = mb_strtolower(trim((string) $this->paqueteria));
        $elegida = $this->opcionEnvioElegida;
        $paqueteria = $elegida?->paqueteria_id && mb_strtolower(trim((string) $elegida->paqueteria_nombre)) === $nombre
            ? $elegida->paqueteria
            : null;

        $paqueteria ??= Paqueteria::query()->whereRaw('lower(nombre) = ?', [$nombre])->first();

        return $paqueteria?->urlRastreo($this->numero_guia)
            ?? PedidoEnviadoMail::urlRastreo($this->paqueteria, $this->numero_guia);
    }

    public function scopeVencibles(Builder $query): Builder
    {
        return $query
            ->where('estatus', EstatusPedido::PendientePago->value)
            ->whereIn('estado_pago', [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value])
            ->whereNotNull('vence_en')
            ->where('vence_en', '<', now());
    }

    public function esContraEntrega(): bool
    {
        return $this->metodo_pago === MetodoPagoClave::EfectivoContraEntrega->value;
    }

    public function esRecogerEnTienda(): bool
    {
        return $this->modalidad_entrega === ModalidadEntrega::RecogerEnTienda->value;
    }

    public function esTerminal(): bool
    {
        return in_array($this->estatus, [
            EstatusPedido::Cancelado->value,
            EstatusPedido::Vencido->value,
            EstatusPedido::Reembolsado->value,
        ], true);
    }

    /**
     * Calcula (sin guardar) el estatus general a partir de estado_pago, estado_logistico y envío.
     * Los estados terminales cancelado/vencido se fijan explícitamente y no se recalculan.
     */
    public function recalcularEstatus(): string
    {
        $actual = $this->estatus;

        if (in_array($actual, [EstatusPedido::Cancelado->value, EstatusPedido::Vencido->value], true)) {
            return $actual;
        }

        if ($this->estado_pago === EstadoPago::Reembolsado->value) {
            return $this->estatus = EstatusPedido::Reembolsado->value;
        }

        $nuevo = match ($this->estado_logistico) {
            EstadoLogistico::Entregado->value => EstatusPedido::Entregado->value,
            EstadoLogistico::Enviado->value => EstatusPedido::Enviado->value,
            EstadoLogistico::ListoEnvio->value, EstadoLogistico::ListoRecoger->value => EstatusPedido::ListoEnvio->value,
            EstadoLogistico::EnPreparacion->value => EstatusPedido::EnPreparacion->value,
            EstadoLogistico::Incidencia->value, EstadoLogistico::Devuelto->value => $actual,
            default => null,
        };

        if ($nuevo === null) {
            $nuevo = match (true) {
                $this->envio_estado === EstadoEnvio::PorCotizar->value => EstatusPedido::PendienteCotizacion->value,
                $this->envio_estado === EstadoEnvio::OpcionesEnviadas->value => EstatusPedido::EleccionEnvio->value,
                in_array($this->estado_pago, [EstadoPago::Aprobado->value, EstadoPago::ContraEntrega->value, EstadoPago::NoAplica->value], true) => EstatusPedido::Confirmado->value,
                $this->estado_pago === EstadoPago::EnRevision->value => EstatusPedido::PagoEnRevision->value,
                default => EstatusPedido::PendientePago->value,
            };
        }

        return $this->estatus = $nuevo;
    }

    /** @return array{value: string, label: string, color: string}|null */
    public function estatusInfo(): ?array
    {
        return EstatusPedido::tryFrom((string) $this->estatus)?->toArray();
    }
}
