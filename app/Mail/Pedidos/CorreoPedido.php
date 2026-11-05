<?php

namespace App\Mail\Pedidos;

use App\Http\Controllers\Cliente\Concerns\PresentaPedido;
use App\Models\Pedido;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pdf\PdfService;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los correos de pedido al cliente: en cola y sólo después del commit.
 * Los datos (incl. bancarios) se leen al renderizar, no se serializan en la cola.
 */
abstract class CorreoPedido extends Mailable implements ShouldQueue
{
    use PresentaPedido, Queueable, SerializesModels;

    public function __construct(public Pedido $pedido)
    {
        $this->afterCommit();
    }

    abstract protected function asunto(): string;

    abstract protected function vista(): string;

    /** @return array<string, mixed> Datos extra para la vista. */
    protected function datos(): array
    {
        return [];
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto());
    }

    public function content(): Content
    {
        $config = app(ConfiguracionTienda::class);

        return new Content(
            view: $this->vista(),
            with: [
                'pedido' => $this->presentarPedido($this->pedido->fresh() ?? $this->pedido, incluirComprobantes: false),
                'tienda' => ['nombre' => $config->nombre(), 'contacto' => $config->contacto()],
                'logoPath' => PdfService::logoPath(),
                'urlPedido' => route('cliente.pedidos.show', $this->pedido),
                ...$this->datos(),
            ],
        );
    }

    /**
     * Mailable copia las propiedades públicas a la vista DESPUÉS de los datos de `content()`, así que
     * `$pedido` llegaba como el modelo (no como el arreglo presentado) y las plantillas mostraban
     * campos vacíos («Método de pago: ·», envío $0.00 sin cupón) o fallaban (aviso al staff).
     *
     * @return array<string, mixed>
     */
    public function buildViewData()
    {
        $data = parent::buildViewData();

        if (is_array($this->viewData['pedido'] ?? null)) {
            $data['pedido'] = $this->viewData['pedido'];
        }

        return $data;
    }

    protected function folio(): string
    {
        return (string) $this->pedido->folio;
    }

    protected static function fechaMx(?CarbonInterface $fecha): ?string
    {
        return $fecha?->copy()->timezone('America/Mexico_City')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a');
    }
}
