<?php

namespace App\Mail\Pedidos;

use App\Models\PedidoOpcionEnvio;

/**
 * "Tu cotización de envío está lista": opciones de paquetería para que el cliente elija.
 */
class CotizacionEnvioListaMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Tu cotización de envío está lista · pedido '.$this->folio();
    }

    protected function vista(): string
    {
        return 'emails.pedidos.cotizacion-envio';
    }

    protected function datos(): array
    {
        $pedido = $this->pedido->fresh() ?? $this->pedido;

        return [
            'opciones' => PedidoOpcionEnvio::presentarLista(
                $pedido->opcionesEnvio()->whereNull('seleccionada_en')->get()
            ),
        ];
    }
}
