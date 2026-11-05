<?php

namespace Database\Factories;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Marca>
 */
class MarcaFactory extends Factory
{
    protected $model = Marca::class;

    public function definition(): array
    {
        $nombre = ucfirst($this->faker->unique()->word()).' '.Str::random(3);

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'activa' => true,
        ];
    }
}
