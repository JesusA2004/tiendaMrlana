<?php

namespace App\Events\Pedidos;

use App\Models\Pedido;
use App\Models\User;

/**
 * Pedido cancelado. Si requiereReembolso=true había un pago aprobado (reembolso manual pendiente).
 */
class PedidoCancelado extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public string $motivo,
        public bool $requiereReembolso = false,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().'';
    }
}
