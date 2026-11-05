<?php

namespace App\Models;

use Database\Factories\PagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `estatus` usa App\Enums\EstadoPagoRegistro para pagos nuevos; los históricos de Openpay
 * (procesando, completado, fallido, ...) se conservan tal cual.
 */
class Pago extends Model
{
    /** @use HasFactory<PagoFactory> */
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'pedido_id',
        'metodo_pago_id',
        'tipo',
        'estatus',
        'monto',
        'moneda',
        'referencia_externa',
        'referencia_cliente',
        'autorizacion',
        'respuesta_pasarela',
        'pagado_en',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
        'notas_internas',
        'monto_recibido',
        'recibido_por',
        'recibido_en',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_recibido' => 'decimal:2',
        'respuesta_pasarela' => 'array',
        'pagado_en' => 'datetime',
        'revisado_en' => 'datetime',
        'recibido_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function transacciones(): HasMany
    {
        return $this->hasMany(TransaccionPasarela::class, 'pago_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(PagoComprobante::class, 'pago_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }
}
