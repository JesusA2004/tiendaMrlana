<?php

namespace App\Mail\Pedidos;

use App\Services\Configuracion\ConfiguracionTienda;

class InstruccionesTransferenciaMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Datos para pagar tu pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.instrucciones-transferencia';
    }

    protected function datos(): array
    {
        return [
            'instrucciones' => $this->instruccionesPago($this->pedido, true, app(ConfiguracionTienda::class)),
            'venceEn' => self::fechaMx($this->pedido->vence_en),
        ];
    }
}
