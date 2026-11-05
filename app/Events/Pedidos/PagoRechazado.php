<?php

namespace App\Events\Pedidos;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;

/**
 * Comprobante/pago rechazado; el cliente puede volver a subir.
 */
class PagoRechazado extends EventoPedido
{
    public function __construct(
        Pedido $pedido,
        public Pago $pago,
        public string $motivo,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().':'.$this->pago->id.':'.$this->pago->updated_at?->timestamp;
    }
}
