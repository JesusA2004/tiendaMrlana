<?php

namespace App\Mail\Pedidos;

use App\Models\Pedido;
use App\Services\Configuracion\ConfiguracionTienda;

class PagoRechazadoMail extends CorreoPedido
{
    public function __construct(Pedido $pedido, public string $motivo)
    {
        parent::__construct($pedido);
    }

    protected function asunto(): string
    {
        return 'Necesitamos revisar tu pago · '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.pago-rechazado';
    }

    protected function datos(): array
    {
        return [
            'motivo' => $this->motivo,
            'instrucciones' => $this->instruccionesPago($this->pedido, true, app(ConfiguracionTienda::class)),
            'venceEn' => self::fechaMx($this->pedido->vence_en),
        ];
    }
}
