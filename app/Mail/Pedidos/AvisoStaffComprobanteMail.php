<?php

namespace App\Mail\Pedidos;

class AvisoStaffComprobanteMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Comprobante por revisar · '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.staff.comprobante';
    }

    /** Aviso interno: enlace al panel, sin datos bancarios. */
    protected function datos(): array
    {
        return [
            'urlAdmin' => url('/admin/pedidos/'.$this->pedido->id),
        ];
    }
}
