<?php

namespace App\Events\Pedidos;

use App\Models\Pago;
use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\User;

/**
 * El cliente subió un comprobante de transferencia (avisar al admin).
 */
class ComprobanteRecibido extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public Pago $pago,
        public PagoComprobante $comprobante,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().':'.$this->comprobante->id;
    }
}
