<?php

namespace App\Events\Pedidos;

use App\Models\Pedido;
use App\Models\User;

/**
 * Se enviaron (o reemplazaron) las opciones de envío; el cliente debe elegir una.
 * `ronda` identifica el juego de opciones (id mayor) para no duplicar el correo de una
 * misma cotización y sí avisar cuando se re-cotiza.
 */
class OpcionesEnvioEnviadas extends EventoPedido
{
    /** @param  list<int>  $opcionIds */
    public function __construct(
        Pedido $pedido,
        public array $opcionIds,
        public bool $reemplazo = false,
        ?User $actor = null,
    ) {
        parent::__construct($pedido, $actor);
    }

    public function claveNotificacion(): string
    {
        return parent::claveNotificacion().':'.($this->opcionIds === [] ? 0 : max($this->opcionIds));
    }
}
