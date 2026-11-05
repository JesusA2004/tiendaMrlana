<?php

namespace Database\Factories;

use App\Models\Paqueteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sólo para pruebas: en producción el catálogo lo da de alta el admin.
 *
 * @extends Factory<Paqueteria>
 */
class PaqueteriaFactory extends Factory
{
    protected $model = Paqueteria::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Paquetería '.fake()->unique()->bothify('??-###'),
            'activo' => true,
            'url_rastreo' => null,
            'notas' => null,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['activo' => false]);
    }

    public function conRastreo(string $url = 'https://rastreo.example.test/guia/{guia}'): static
    {
        return $this->state(['url_rastreo' => $url]);
    }
}
