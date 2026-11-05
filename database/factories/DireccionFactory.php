<?php

namespace Database\Factories;

use App\Models\Direccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Direccion>
 */
class DireccionFactory extends Factory
{
    protected $model = Direccion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'alias' => 'Casa',
            'nombre_receptor' => $this->faker->name(),
            'telefono' => '5550000000',
            'calle' => 'Calle Prueba',
            'numero_exterior' => '10',
            'numero_interior' => null,
            'colonia' => 'Centro',
            'municipio' => 'Cuernavaca',
            'estado' => 'Morelos',
            'pais' => 'México',
            'codigo_postal' => '62000',
            'referencias' => null,
            'predeterminada' => true,
        ];
    }

    public function enEstado(string $estado, string $cp): static
    {
        return $this->state(['estado' => $estado, 'codigo_postal' => $cp]);
    }
}
