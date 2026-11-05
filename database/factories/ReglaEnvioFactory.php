<?php

namespace Database\Factories;

use App\Enums\TipoReglaEnvio;
use App\Models\ReglaEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReglaEnvio>
 */
class ReglaEnvioFactory extends Factory
{
    protected $model = ReglaEnvio::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Tarifa general',
            'tipo' => TipoReglaEnvio::TarifaFija->value,
            'estados' => null,
            'cp_prefijos' => null,
            'monto' => 150,
            'gratis_desde' => null,
            'dias_estimados' => '3 a 5 días hábiles',
            'permite_contra_entrega' => false,
            'activo' => true,
            'prioridad' => 0,
        ];
    }

    public function cotizacionManual(): static
    {
        return $this->state(['tipo' => TipoReglaEnvio::CotizacionManual->value, 'monto' => null]);
    }

    public function recoger(): static
    {
        return $this->state(['nombre' => 'Recoger en tienda', 'tipo' => TipoReglaEnvio::RecogerEnTienda->value, 'monto' => 0]);
    }
}
