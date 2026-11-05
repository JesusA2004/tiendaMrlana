<?php

namespace App\Mail\Pedidos;

use App\Models\Pedido;

class PedidoCanceladoMail extends CorreoPedido
{
    public function __construct(Pedido $pedido, public string $motivo, public bool $requiereReembolso = false)
    {
        parent::__construct($pedido);
    }

    protected function asunto(): string
    {
        return 'Tu pedido '.$this->folio().' fue cancelado';
    }

    protected function vista(): string
    {
        return 'emails.pedidos.cancelado';
    }

    protected function datos(): array
    {
        return [
            'motivo' => $this->motivo,
            'requiereReembolso' => $this->requiereReembolso,
        ];
    }
}
