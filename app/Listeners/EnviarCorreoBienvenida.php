<?php

namespace App\Listeners;

use App\Mail\BienvenidaMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correo de bienvenida al registrarse (sin contraseñas). En cola, después del commit.
 */
class EnviarCorreoBienvenida
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || blank($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->queue(new BienvenidaMail($user));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
