<?php

namespace App\Mail;

use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pdf\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Cuenta creada por un administrador: SÓLO envía un enlace para definir la contraseña.
 *
 * Seguridad: antes se enviaba la contraseña temporal en texto plano. El parámetro
 * `$temporaryPassword` se conserva por compatibilidad con Admin\UsuarioController pero
 * se IGNORA (no se guarda ni se muestra). Operación debe dejar de pasarlo.
 */
class AdminCreatedUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $email,
        ?string $temporaryPassword = null,
        public string $resetUrl = '',
        public string $role = 'cliente',
    ) {
        unset($temporaryPassword);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activa tu cuenta en '.app(ConfiguracionTienda::class)->nombre());
    }

    public function content(): Content
    {
        $config = app(ConfiguracionTienda::class);

        return new Content(
            view: 'emails.admin-created-user',
            with: [
                'tienda' => ['nombre' => $config->nombre(), 'contacto' => $config->contacto()],
                'logoPath' => PdfService::logoPath(),
                'rolLabel' => match ($this->role) {
                    'admin' => 'Administrador',
                    'vendedor' => 'Vendedor',
                    default => 'Cliente',
                },
            ],
        );
    }
}
