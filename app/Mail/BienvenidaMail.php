<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pdf\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Bienvenida al crear la cuenta (nunca incluye contraseñas).
 */
class BienvenidaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '¡Bienvenido a '.app(ConfiguracionTienda::class)->nombre().'!');
    }

    public function content(): Content
    {
        $config = app(ConfiguracionTienda::class);

        return new Content(
            view: 'emails.bienvenida',
            with: [
                'nombre' => $this->user->name,
                'tienda' => ['nombre' => $config->nombre(), 'contacto' => $config->contacto()],
                'logoPath' => PdfService::logoPath(),
                'urlTienda' => route('tienda.catalogo'),
                'urlPedidos' => route('cliente.pedidos.index'),
            ],
        );
    }
}
