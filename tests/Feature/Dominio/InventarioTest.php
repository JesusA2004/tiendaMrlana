<?php

use App\Exceptions\StockInsuficienteException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\InventarioMovimiento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\Inventario\InventarioService;
use App\Services\Pedidos\PedidoWorkflow;

beforeEach(fn () => sembrarMetodosPago());

it('rechaza el pedido si no hay stock suficiente', function () {
    $p = Producto::factory()->stock(2)->create();

    expect(fn () => crearPedidoCheckout(clienteTienda(), [[$p, 3]]))
        ->toThrow(TransicionInvalidaException::class, 'Sólo hay 2');

    expect(Pedido::count())->toBe(0)->and($p->fresh()->stock)->toBe(2);
});

it('la reserva con lock detecta stock que cambió después del cálculo', function () {
    $p = Producto::factory()->stock(1)->create();
    $pedido = Pedido::factory()->create();
    $pedido->items()->create(['producto_id' => $p->id, 'sku' => $p->sku, 'nombre' => $p->nombre, 'cantidad' => 2, 'precio_unitario' => 1, 'subtotal' => 2]);

    expect(fn () => app(InventarioService::class)->reservarParaPedido($pedido))
        ->toThrow(StockInsuficienteException::class);
});

it('reserva stock al crear el pedido y lo libera al cancelar', function () {
    $p = Producto::factory()->stock(5)->create();
    $pedido = crearPedidoCheckout(clienteTienda(), [[$p, 3]]);

    expect($p->fresh()->stock)->toBe(2)
        ->and($pedido->stock_reservado)->toBeTrue()
        ->and((int) InventarioMovimiento::where('tipo', 'venta')->sum('cantidad'))->toBe(-3);

    app(PedidoWorkflow::class)->cancelar($pedido, adminTienda(), 'Cliente desistió');

    expect($p->fresh()->stock)->toBe(5)
        ->and($pedido->fresh()->stock_reservado)->toBeFalse()
        ->and($pedido->fresh()->estatus)->toBe('cancelado')
        ->and((int) InventarioMovimiento::where('tipo', 'liberacion')->sum('cantidad'))->toBe(3);
});

it('bajo pedido no descuenta stock', function () {
    $p = Producto::factory()->bajoPedido()->create();

    crearPedidoCheckout(clienteTienda(), [[$p, 4]]);

    expect($p->fresh()->stock)->toBe(0)->and(InventarioMovimiento::count())->toBe(0);
});

it('registra movimientos de entrada, salida y ajuste', function () {
    $p = Producto::factory()->stock(10)->create();
    $inv = app(InventarioService::class);
    $admin = adminTienda();

    $inv->entrada($p, 5, $admin, 'Compra');
    $inv->salida($p, 3, $admin, 'Merma');
    $mov = $inv->establecer($p, 20, $admin, 'Conteo');

    expect($p->stock)->toBe(20)
        ->and($mov->cantidad)->toBe(8)
        ->and($mov->stock_anterior)->toBe(12)
        ->and(InventarioMovimiento::count())->toBe(3)
        ->and(fn () => $inv->salida($p, 50))->toThrow(StockInsuficienteException::class);
});
