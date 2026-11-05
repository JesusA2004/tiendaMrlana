<?php

use App\Models\Categoria;
use App\Models\Cupon;
use App\Models\Direccion;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Services\Carrito\CarritoService;
use App\Services\Pedidos\PedidoWorkflow;

beforeEach(fn () => sembrarMetodosPago());

it('calcula el total con ofertas, cupón y envío por regla', function () {
    $cat = Categoria::factory()->create();
    $silla = Producto::factory()->precio(1000)->for($cat)->create();
    $mesa = Producto::factory()->precio(500)->create();
    Oferta::create(['nombre' => '20% sillas', 'tipo' => 'porcentaje', 'valor' => 20, 'aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'activa' => true]);
    Cupon::factory()->montoFijo(100)->create(['codigo' => 'MENOS100']);
    ReglaEnvio::factory()->create(['monto' => 199, 'estados' => ['Morelos']]);

    $cliente = clienteTienda();
    $pedido = crearPedidoCheckout($cliente, [[$silla, 2], [$mesa, 1]], cupon: 'MENOS100');

    // silla 800 x2 = 1600 + mesa 500 = 2100; cupón -100; envío +199
    expect((float) $pedido->subtotal)->toBe(2100.0)
        ->and((float) $pedido->descuento)->toBe(500.0) // 400 ofertas + 100 cupón
        ->and((float) $pedido->envio)->toBe(199.0)
        ->and((float) $pedido->total)->toBe(2199.0)
        ->and((float) $pedido->saldo_pendiente)->toBe(2199.0)
        ->and($pedido->envio_estado)->toBe('calculado')
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->cupon_codigo)->toBe('MENOS100')
        ->and($pedido->items)->toHaveCount(2)
        ->and((float) $pedido->items->firstWhere('producto_id', $silla->id)->precio_unitario)->toBe(800.0)
        ->and($pedido->vence_en)->not->toBeNull()
        ->and($pedido->pagos()->first()->tipo)->toBe('transferencia');
});

it('deja el envío por cotizar cuando no hay reglas', function () {
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->precio(800)->create(), 1]]);

    expect($pedido->envio_estado)->toBe('por_cotizar')
        ->and($pedido->estatus)->toBe('pendiente_cotizacion')
        ->and((float) $pedido->envio)->toBe(0.0)
        ->and((float) $pedido->total)->toBe(800.0)
        ->and($pedido->vence_en)->toBeNull();
});

it('deja por cotizar cuando la regla no cubre el estado del cliente', function () {
    ReglaEnvio::factory()->create(['estados' => ['Jalisco']]);
    $cliente = clienteTienda();
    $dir = Direccion::factory()->for($cliente)->enEstado('Morelos', '62000')->create();

    $pedido = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]], direccion: $dir);

    expect($pedido->envio_estado)->toBe('por_cotizar');
});

it('aplica envío sin costo sólo cuando la regla define gratis_desde', function () {
    ReglaEnvio::factory()->create(['monto' => 150, 'gratis_desde' => 1000]);

    $caro = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->precio(1200)->create(), 1]]);
    $barato = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->precio(300)->create(), 1]]);

    expect((float) $caro->envio)->toBe(0.0)->and((float) $barato->envio)->toBe(150.0);
});

it('cotizar el envío recalcula el total y pasa a pendiente de pago', function () {
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->precio(800)->create(), 1]]);

    $pedido = app(PedidoWorkflow::class)->cotizarEnvio($pedido, 250, vendedorTienda());

    expect($pedido->envio_estado)->toBe('cotizado')
        ->and((float) $pedido->total)->toBe(1050.0)
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->vence_en)->not->toBeNull()
        ->and((float) $pedido->pagos()->first()->monto)->toBe(1050.0);
});

it('el resumen del carrito recalcula desde BD aunque la sesión traiga subtotales viejos', function () {
    $p = Producto::factory()->precio(400)->create();
    session()->put('carrito', [$p->id => ['producto_id' => $p->id, 'cantidad' => 2, 'subtotal' => 1, 'precio' => 0.5]]);

    $r = app(CarritoService::class)->resumen();

    expect($r['resumen']['subtotal'])->toBe(800.0)
        ->and($r['resumen']['envio_por_cotizar'])->toBeTrue()
        ->and(app(CarritoService::class)->cantidadItems())->toBe(2);
});
