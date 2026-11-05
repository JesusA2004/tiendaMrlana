<?php

namespace App\Mail\Pedidos;

use App\Services\Configuracion\ConfiguracionTienda;

class EnvioCotizadoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Ya tenemos el costo de envío de tu pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.envio-cotizado';
    }

    protected function datos(): array
    {
        return [
            'instrucciones' => $this->instruccionesPago($this->pedido, true, app(ConfiguracionTienda::class)),
            'venceEn' => self::fechaMx($this->pedido->vence_en),
        ];
    }
}
