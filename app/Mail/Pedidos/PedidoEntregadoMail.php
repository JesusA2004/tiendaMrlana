<?php

namespace App\Mail\Pedidos;

class PedidoEntregadoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Tu pedido '.$this->folio().' fue entregado';
    }

    protected function vista(): string
    {
        return 'emails.pedidos.entregado';
    }
}
