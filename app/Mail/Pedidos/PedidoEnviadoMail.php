<?php

namespace App\Mail\Pedidos;

use Illuminate\Support\Str;

class PedidoEnviadoMail extends CorreoPedido
{
    protected function asunto(): string
    {
        return 'Tu pedido '.$this->folio().' va en camino';
    }

    protected function vista(): string
    {
        return 'emails.pedidos.enviado';
    }

    protected function datos(): array
    {
        return [
            // Catálogo de paqueterías (URL con {guia}); si no, paqueterías conocidas.
            'urlRastreo' => ($this->pedido->fresh() ?? $this->pedido)->urlRastreo(),
            'fechaEnvio' => $this->pedido->fecha_envio?->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
        ];
    }

    /** Enlaces públicos de rastreo de paqueterías conocidas (respaldo si el catálogo no tiene URL). */
    public static function urlRastreo(?string $paqueteria, ?string $guia): ?string
    {
        if (blank($paqueteria) || blank($guia)) {
            return null;
        }

        $carrier = Str::of((string) $paqueteria)->lower()->ascii()->toString();
        $guia = urlencode(trim((string) $guia));

        return match (true) {
            str_contains($carrier, 'dhl') => "https://www.dhl.com/mx-es/home/tracking/tracking-express.html?submit=1&tracking-id={$guia}",
            str_contains($carrier, 'estafeta') => "https://www.estafeta.com/Herramientas/Rastreo?trackingNumber={$guia}",
            str_contains($carrier, 'fedex') => "https://www.fedex.com/fedextrack/?trknbr={$guia}",
            str_contains($carrier, 'redpack') => "https://www.redpack.com.mx/rastreo/?guias={$guia}",
            str_contains($carrier, 'ups') => "https://www.ups.com/track?tracknum={$guia}",
            default => null,
        };
    }
}
