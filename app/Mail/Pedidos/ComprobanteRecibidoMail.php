<?php

namespace App\Mail\Pedidos;

class ComprobanteRecibidoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Recibimos tu comprobante · '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.comprobante-recibido';
    }
}
