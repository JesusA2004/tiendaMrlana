<?php

namespace App\Events\Pedidos;

use App\Models\Pedido;
use App\Models\PedidoOpcionEnvio;
use App\Models\User;

/**
 * El cliente eligió una opción de envío: el total es final y el pedido ya puede pagarse.
 */
class EnvioElegido extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public PedidoOpcionEnvio $opcion,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().':'.$this->opcion->id;
    }
}
