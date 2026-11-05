<?php

namespace App\Events\Pedidos;

use App\Models\Pedido;
use App\Models\User;

/**
 * Se cotizó manualmente el envío; el pedido ya puede pagarse.
 */
class EnvioCotizado extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public float $monto,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().'';
    }
}
