<?php

namespace Database\Factories;

use App\Enums\EstadoPagoRegistro;
use App\Enums\MetodoPagoClave;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'pedido_id' => Pedido::factory(),
            'metodo_pago_id' => fn () => MetodoPago::query()->firstOrCreate(
                ['clave' => MetodoPagoClave::Transferencia->value],
                ['nombre' => 'Transferencia bancaria', 'activo' => true, 'configuracion' => []],
            )->id,
            'tipo' => MetodoPagoClave::Transferencia->value,
            'estatus' => EstadoPagoRegistro::Pendiente->value,
            'monto' => 1000,
            'moneda' => 'MXN',
        ];
    }
}
