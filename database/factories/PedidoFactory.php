<?php

namespace Database\Factories;

use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Pedido "pendiente de pago" por transferencia sin items. Para un pedido completo usar
 * PedidoWorkflow::crearDesdeCheckout en las pruebas.
 *
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    protected $model = Pedido::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'direccion_id' => null,
            'folio' => 'ML-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'estatus' => EstatusPedido::PendientePago->value,
            'estado_pago' => EstadoPago::Pendiente->value,
            'estado_logistico' => EstadoLogistico::Pendiente->value,
            'metodo_pago' => MetodoPagoClave::Transferencia->value,
            'modalidad_entrega' => ModalidadEntrega::EnvioDomicilio->value,
            'envio_estado' => 'calculado',
            'moneda' => 'MXN',
            'subtotal' => 1000,
            'descuento' => 0,
            'envio' => 0,
            'impuesto' => 0,
            'total' => 1000,
            'saldo_pendiente' => 1000,
            'nombre_cliente' => $this->faker->name(),
            'correo_cliente' => $this->faker->safeEmail(),
            'telefono_cliente' => '5550000000',
            'vence_en' => now()->addHours(48),
        ];
    }

    public function aprobado(): static
    {
        return $this->state([
            'estatus' => EstatusPedido::Confirmado->value,
            'estado_pago' => EstadoPago::Aprobado->value,
            'saldo_pendiente' => 0,
            'pagado_en' => now(),
            'vence_en' => null,
        ]);
    }
}
