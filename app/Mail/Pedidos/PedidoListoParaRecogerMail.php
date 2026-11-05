<?php

namespace App\Mail\Pedidos;

use App\Services\Configuracion\ConfiguracionTienda;

class PedidoListoParaRecogerMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Tu pedido '.$this->folio().' está listo para recoger';
    }

    protected function vista(): string
    {
        return 'emails.pedidos.listo-recoger';
    }

    protected function datos(): array
    {
        return [
            'instrucciones' => $this->instruccionesPago($this->pedido, true, app(ConfiguracionTienda::class)),
            'venceEn' => self::fechaMx($this->pedido->vence_en),
        ];
    }
}
