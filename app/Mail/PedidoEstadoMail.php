<?php

namespace App\Mail;

use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * @deprecated Reemplazado por los correos de App\Mail\Pedidos (listeners de eventos de dominio).
 *             Sólo lo referencia PaymentService (Openpay, deshabilitado). No usar.
 */
class PedidoEstadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Pedido $pedido;

    public string $tipo;

    public ?string $urlRastreo;

    public function __construct(Pedido $pedido, string $tipo)
    {
        $this->pedido = $pedido->loadMissing(['items', 'direccion', 'pagos.metodoPago']);
        $this->tipo = $tipo;
        $this->urlRastreo = $this->resolverUrlRastreo($pedido->paqueteria, $pedido->numero_guia);
    }

    public function build(): self
    {
        return $this
            ->subject($this->correoSubject())
            ->view('emails.pedidos.estado')
            ->with([
                'pedido' => $this->pedido,
                'tipo' => $this->tipo,
                'urlRastreo' => $this->urlRastreo,
                'contenido' => $this->contenido(),
            ]);
    }

    private function correoSubject(): string
    {
        return match ($this->tipo) {
            'pagado' => 'Recibimos tu pago · Pedido '.$this->pedido->folio,
            'preparando' => 'Estamos preparando tu pedido '.$this->pedido->folio,
            'enviado' => 'Tu pedido '.$this->pedido->folio.' está en camino',
            'entregado' => 'Tu pedido '.$this->pedido->folio.' fue entregado',
            default => 'Actualización de tu pedido '.$this->pedido->folio,
        };
    }

    private function contenido(): array
    {
        return match ($this->tipo) {
            'pagado' => [
                'eyebrow' => 'Pago confirmado',
                'titulo' => 'Tu pago fue recibido correctamente',
                'mensaje' => 'Gracias por tu compra. Ya registramos el pago de tu pedido y pronto comenzaremos con la preparación.',
                'badge' => 'Pagado',
            ],
            'preparando' => [
                'eyebrow' => 'Pedido en preparación',
                'titulo' => 'Estamos preparando tu pedido',
                'mensaje' => 'Tu compra ya se encuentra en proceso de preparación. Te avisaremos cuando sea entregada a paquetería.',
                'badge' => 'Preparando',
            ],
            'enviado' => [
                'eyebrow' => 'Pedido en camino',
                'titulo' => 'Tu pedido ya fue enviado',
                'mensaje' => 'Tu pedido fue entregado a paquetería. Te compartimos los datos de rastreo para que puedas darle seguimiento.',
                'badge' => 'Enviado',
            ],
            'entregado' => [
                'eyebrow' => 'Pedido entregado',
                'titulo' => 'Tu pedido fue entregado',
                'mensaje' => 'El pedido aparece como entregado. Gracias por comprar en Mr Lana.',
                'badge' => 'Entregado',
            ],
            default => [
                'eyebrow' => 'Actualización',
                'titulo' => 'Tu pedido fue actualizado',
                'mensaje' => 'Tenemos una actualización sobre tu pedido.',
                'badge' => 'Actualizado',
            ],
        };
    }

    private function resolverUrlRastreo(?string $paqueteria, ?string $guia): ?string
    {
        if (! $paqueteria || ! $guia) {
            return null;
        }

        $carrier = Str::of($paqueteria)->lower()->ascii()->toString();
        $guia = urlencode(trim($guia));

        if (str_contains($carrier, 'dhl')) {
            return "https://www.dhl.com/mx-es/home/tracking/tracking-express.html?submit=1&tracking-id={$guia}";
        }

        if (str_contains($carrier, 'estafeta')) {
            return "https://www.estafeta.com/Herramientas/Rastreo?trackingNumber={$guia}";
        }

        if (str_contains($carrier, 'fedex')) {
            return "https://www.fedex.com/fedextrack/?trknbr={$guia}";
        }

        if (str_contains($carrier, 'redpack')) {
            return "https://www.redpack.com.mx/rastreo/?guias={$guia}";
        }

        if (str_contains($carrier, 'ups')) {
            return "https://www.ups.com/track?tracknum={$guia}";
        }

        return null;
    }
}
