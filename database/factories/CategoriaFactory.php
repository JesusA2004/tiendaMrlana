<?php

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Categoria>
 */
class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        $nombre = ucfirst($this->faker->unique()->word()).' '.Str::random(3);

        return [
            'categoria_padre_id' => null,
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'descripcion' => null,
            'activa' => true,
            'orden' => 0,
        ];
    }
}
