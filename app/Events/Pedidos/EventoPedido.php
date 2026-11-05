<?php

namespace App\Events\Pedidos;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los eventos de dominio de pedidos. Se despachan DESPUÉS del commit de la
 * transacción del PedidoWorkflow (ShouldDispatchAfterCommit). Los listeners/correos
 * los implementa el equipo de notificaciones (usar NotificacionPedido::registrarUnaVez para deduplicar).
 */
abstract class EventoPedido implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Pedido $pedido,
        public ?User $actor = null,
    ) {}

    /** Clave sugerida para deduplicar la notificación. */
    public function claveNotificacion(): string
    {
        return class_basename(static::class).':'.$this->pedido->id;
    }
}
