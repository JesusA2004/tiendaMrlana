<?php

namespace App\Mail\Pedidos;

class AvisoStaffPedidoNuevoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Nuevo pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.staff.pedido-nuevo';
    }

    /** Aviso interno: enlace al panel, sin datos bancarios. */
    protected function datos(): array
    {
        return [
            'urlAdmin' => url('/admin/pedidos/'.$this->pedido->id),
        ];
    }
}
