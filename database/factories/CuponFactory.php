<?php

namespace Database\Factories;

use App\Models\Cupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cupon>
 */
class CuponFactory extends Factory
{
    protected $model = Cupon::class;

    public function definition(): array
    {
        return [
            'codigo' => 'CUP'.Str::upper(Str::random(6)),
            'nombre' => 'Cupón de prueba',
            'tipo' => 'porcentaje',
            'valor' => 10,
            'compra_minima' => null,
            'usos_maximos' => null,
            'usos_por_usuario' => null,
            'usos_actuales' => 0,
            'inicia_en' => null,
            'termina_en' => null,
            'activo' => true,
        ];
    }

    public function montoFijo(float $valor): static
    {
        return $this->state(['tipo' => 'monto_fijo', 'valor' => $valor]);
    }

    public function vencido(): static
    {
        return $this->state(['termina_en' => now()->subDay()]);
    }

    public function agotado(): static
    {
        return $this->state(['usos_maximos' => 1, 'usos_actuales' => 1]);
    }
}
