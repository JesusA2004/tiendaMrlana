<?php

namespace App\Events\Pedidos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;

/**
 * Pago aprobado (transferencia revisada o efectivo confirmado).
 */
class PagoAprobado extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public Pago $pago,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().':'.$this->pago->id;
    }
}
