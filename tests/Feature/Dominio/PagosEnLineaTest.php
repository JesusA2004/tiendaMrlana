<?php

use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\Payments\Data\PaymentChargeData;
use App\Services\Payments\Exceptions\OnlinePaymentsDisabledException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => sembrarMetodosPago());

it('los pagos en línea están deshabilitados por configuración', function () {
    expect(config('payments.enabled'))->toBeFalse();
});

it('PaymentService::charge lanza OnlinePaymentsDisabledException', function () {
    $pago = Pago::factory()->create();

    expect(fn () => app(PaymentService::class)->charge($pago->pedido, $pago, new PaymentChargeData(
        tokenId: 'tok', deviceSessionId: 'dev', amount: 100, currency: 'MXN', description: 'x',
        customerName: 'a', customerEmail: 'a@b.c', customerPhone: null,
    )))->toThrow(OnlinePaymentsDisabledException::class);

    expect($pago->fresh()->estatus)->toBe('pendiente');
});

it('PaymentGatewayManager no entrega pasarelas', function () {
    expect(fn () => app(PaymentGatewayManager::class)->gateway())->toThrow(OnlinePaymentsDisabledException::class);
});

it('la ruta de pago con tarjeta ya no existe', function () {
    $cliente = clienteTienda();
    $pedido = Pedido::factory()->for($cliente)->create();

    expect(Route::has('checkout.pagar'))->toBeFalse();

    $this->actingAs($cliente)
        ->post("/checkout/gracias/{$pedido->id}/pagar", ['token_id' => 'x', 'device_session_id' => 'y'])
        ->assertNotFound();
});

it('el seeder deja Openpay inactivo y bloqueado', function () {
    $openpay = MetodoPago::where('clave', 'openpay')->first();

    expect($openpay->activo)->toBeFalse()
        ->and($openpay->configuracion['bloqueado'])->toBeTrue()
        ->and(MetodoPago::where('clave', 'transferencia')->value('activo'))->toBeTrue()
        ->and(MetodoPago::where('clave', 'efectivo_contra_entrega')->value('activo'))->toBeFalse();

    // Idempotente: correrlo de nuevo no duplica ni reactiva.
    sembrarMetodosPago();
    expect(MetodoPago::count())->toBe(4);
});

it('el admin no puede activar Openpay desde el panel', function () {
    $openpay = MetodoPago::where('clave', 'openpay')->first();

    $this->actingAs(adminTienda())
        ->put(route('admin.metodos-pago.update', $openpay), ['nombre' => 'Openpay', 'clave' => 'openpay', 'activo' => true])
        ->assertSessionHasErrors('activo');

    expect($openpay->fresh()->activo)->toBeFalse();
});
