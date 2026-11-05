<?php

namespace App\Mail\Pedidos;

class PagoAprobadoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return '¡Pago confirmado! Pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.pago-aprobado';
    }
}
