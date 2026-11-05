<?php

namespace App\Mail\Pedidos;

class PedidoEnPreparacionMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Estamos preparando tu pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.en-preparacion';
    }
}
