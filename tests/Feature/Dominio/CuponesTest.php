<?php

use App\Exceptions\CuponInvalidoException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\Producto;
use App\Services\Cupones\CuponService;

beforeEach(fn () => sembrarMetodosPago());

it('valida un cupón vigente y calcula el descuento', function () {
    Cupon::factory()->create(['codigo' => 'BIENVENIDA', 'tipo' => 'porcentaje', 'valor' => 15]);

    $r = app(CuponService::class)->validar(' bienvenida ', 2000);

    expect($r['codigo'])->toBe('BIENVENIDA')->and($r['descuento_aplicado'])->toBe(300.0);
});

it('rechaza cupones inválidos con mensaje claro', function (array $estado, float $subtotal, string $mensaje) {
    Cupon::factory()->create(array_merge(['codigo' => 'X1'], $estado));

    expect(fn () => app(CuponService::class)->validar('X1', $subtotal))
        ->toThrow(CuponInvalidoException::class, $mensaje);
})->with([
    'vencido' => [['termina_en' => now()->subDay()], 1000, 'venció'],
    'aún no vigente' => [['inicia_en' => now()->addDay()], 1000, 'todavía no está vigente'],
    'agotado' => [['usos_maximos' => 2, 'usos_actuales' => 2], 1000, 'límite de usos'],
    'mínimo de compra' => [['compra_minima' => 1500], 1000, 'compra mínima'],
    'inactivo' => [['activo' => false], 1000, 'no existe o no está disponible'],
]);

it('rechaza un código inexistente', function () {
    expect(fn () => app(CuponService::class)->validar('NOEXISTE', 1000))
        ->toThrow(CuponInvalidoException::class);
});

it('respeta el límite de usos por usuario', function () {
    $cliente = clienteTienda();
    $cupon = Cupon::factory()->create(['codigo' => 'UNAVEZ', 'usos_por_usuario' => 1]);
    $producto = Producto::factory()->create();

    crearPedidoCheckout($cliente, [[$producto, 1]], cupon: 'UNAVEZ');

    expect(CuponUso::query()->where('cupon_id', $cupon->id)->count())->toBe(1)
        ->and($cupon->fresh()->usos_actuales)->toBe(1)
        ->and(fn () => app(CuponService::class)->validar('UNAVEZ', 1000, $cliente))
        ->toThrow(CuponInvalidoException::class, 'máximo de veces');
});

it('no permite crear un pedido con un cupón agotado', function () {
    Cupon::factory()->agotado()->create(['codigo' => 'AGOTADO']);

    expect(fn () => crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]], cupon: 'AGOTADO'))
        ->toThrow(TransicionInvalidaException::class, 'límite de usos');
});
