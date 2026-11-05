<?php

namespace App\Mail\Pedidos;

class PedidoVencidoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Tu pedido '.$this->folio().' venció';
    }

    protected function vista(): string
    {
        return 'emails.pedidos.vencido';
    }
}
