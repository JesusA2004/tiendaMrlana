<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeders seguros para PRODUCCIÓN (idempotentes, sin usuarios ni contraseñas).
 * El administrador se crea con: php artisan tienda:crear-admin
 * Datos de demostración (sólo local): php artisan db:seed --class=ProductoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MetodosPagoSeeder::class);

        // Importación del catálogo de muebles (la implementa el equipo de catálogo).
        if (class_exists(CatalogoMueblesSeeder::class)) {
            $this->call(CatalogoMueblesSeeder::class);
        }

        if (app()->environment('local')) {
            $this->command?->info('Entorno local: para datos de demostración ejecuta `php artisan db:seed --class=ProductoSeeder`.');
        }
    }
}
