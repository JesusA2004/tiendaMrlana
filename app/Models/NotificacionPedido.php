<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Deduplicación de correos/avisos por pedido (clave única, p. ej. "pago_aprobado:15:42").
 */
class NotificacionPedido extends Model
{
    protected $table = 'notificaciones_pedido';

    protected $fillable = [
        'pedido_id',
        'tipo',
        'clave',
        'enviado_en',
    ];

    protected $casts = [
        'enviado_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    /**
     * Registra la notificación si no existía. Devuelve true sólo la primera vez
     * (úsese antes de enviar un correo para no duplicarlo).
     */
    public static function registrarUnaVez(Pedido $pedido, string $tipo, string $clave): bool
    {
        try {
            $registro = static::query()->firstOrCreate(
                ['clave' => $clave],
                ['pedido_id' => $pedido->id, 'tipo' => $tipo, 'enviado_en' => now()],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return $registro->wasRecentlyCreated;
    }
}
