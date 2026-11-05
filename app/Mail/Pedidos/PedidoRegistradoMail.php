<?php

namespace App\Mail\Pedidos;

use App\Services\Configuracion\ConfiguracionTienda;

class PedidoRegistradoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Recibimos tu pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.registrado';
    }

    protected function datos(): array
    {
        return [
            'instrucciones' => $this->instruccionesPago($this->pedido, true, app(ConfiguracionTienda::class)),
            'venceEn' => self::fechaMx($this->pedido->vence_en),
        ];
    }
}
