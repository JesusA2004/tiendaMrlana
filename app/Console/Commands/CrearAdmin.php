<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Crea (o promueve) el usuario administrador. Nunca usa contraseñas por defecto.
 *
 * php artisan tienda:crear-admin
 * php artisan tienda:crear-admin --nombre="Ana" --correo=ana@ejemplo.com --password="..." (no interactivo)
 */
class CrearAdmin extends Command
{
    protected $signature = 'tienda:crear-admin
        {--nombre= : Nombre completo}
        {--correo= : Correo electrónico}
        {--password= : Contraseña (si se omite se pide de forma oculta)}
        {--rol=admin : admin o vendedor}
        {--actualizar : Si el correo ya existe, actualiza rol y contraseña}';

    protected $description = 'Crea un usuario administrador (o vendedor) de la tienda de forma segura';

    public function handle(): int
    {
        $rol = (string) $this->option('rol');

        if (! in_array($rol, [User::ROL_ADMIN, User::ROL_VENDEDOR], true)) {
            $this->error('El rol debe ser admin o vendedor.');

            return self::FAILURE;
        }

        $interactivo = $this->input->isInteractive();
        $nombre = $this->option('nombre') ?: ($interactivo ? $this->ask('Nombre completo') : null);
        $correo = $this->option('correo') ?: ($interactivo ? $this->ask('Correo electrónico') : null);
        $password = $this->option('password');

        if (! $password && $interactivo) {
            $password = $this->secret('Contraseña (mín. 12 caracteres, mayúsculas, minúsculas, números y símbolos)');
            $confirmacion = $this->secret('Confirma la contraseña');

            if ($password !== $confirmacion) {
                $this->error('Las contraseñas no coinciden.');

                return self::FAILURE;
            }
        }

        $correo = mb_strtolower(trim((string) $correo));
        $existente = User::query()->where('email', $correo)->first();

        $validador = Validator::make(
            ['nombre' => $nombre, 'correo' => $correo, 'password' => $password],
            [
                'nombre' => ['required', 'string', 'max:191'],
                'correo' => ['required', 'email', 'max:191'],
                'password' => ['required', 'string', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            ],
            [],
            ['nombre' => 'nombre', 'correo' => 'correo', 'password' => 'contraseña'],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (in_array(mb_strtolower((string) $password), ['password', 'secret', '12345678', 'admin'], true)) {
            $this->error('La contraseña es demasiado común.');

            return self::FAILURE;
        }

        if ($existente && ! $this->option('actualizar')) {
            $this->error("Ya existe un usuario con el correo {$correo}. Usa --actualizar para cambiar su rol y contraseña.");

            return self::FAILURE;
        }

        $usuario = $existente ?? new User;
        $usuario->forceFill([
            'name' => trim((string) $nombre),
            'email' => $correo,
            'rol' => $rol,
            'password' => Hash::make((string) $password),
            'email_verified_at' => $usuario->email_verified_at ?? now(),
        ])->save();

        $this->info(($existente ? 'Usuario actualizado' : 'Usuario creado').": {$usuario->email} ({$rol}).");

        return self::SUCCESS;
    }
}
