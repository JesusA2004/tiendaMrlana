<?php

use App\Models\Pedido;
use App\Models\Producto;

it('publicados() excluye borradores, pausados, ocultos y sin precio', function () {
    $publicado = Producto::factory()->create();
    Producto::factory()->borrador()->create();
    Producto::factory()->pausado()->create();
    Producto::factory()->pendienteAprobacion()->create();
    Producto::factory()->create(['visible' => false]);

    expect(Producto::query()->publicados()->pluck('id')->all())->toBe([$publicado->id]);
});

it('vendibles() incluye bajo pedido sin stock y excluye agotados de inventario', function () {
    $conStock = Producto::factory()->create();
    $bajoPedido = Producto::factory()->bajoPedido()->create();
    Producto::factory()->agotado()->create();

    expect(Producto::query()->vendibles()->orderBy('id')->pluck('id')->all())->toBe([$conStock->id, $bajoPedido->id]);
});

it('faltantesParaPublicar lista lo que falta', function () {
    $p = Producto::factory()->borrador()->create(['categoria_id' => null]);

    expect($p->faltantesParaPublicar())->toHaveCount(3)
        ->and(Producto::factory()->create()->faltantesParaPublicar())->toBe([]);
});

it('estaDisponible respeta stock y bajo pedido', function () {
    expect(Producto::factory()->stock(2)->create()->estaDisponible(3))->toBeFalse()
        ->and(Producto::factory()->stock(2)->create()->estaDisponible(2))->toBeTrue()
        ->and(Producto::factory()->bajoPedido()->create()->estaDisponible(5))->toBeTrue();
});

it('recalcularEstatus deriva el estado general', function () {
    $p = new Pedido(['estatus' => 'pendiente_pago', 'estado_pago' => 'en_revision', 'estado_logistico' => 'pendiente', 'envio_estado' => 'calculado']);
    expect($p->recalcularEstatus())->toBe('pago_en_revision');

    $p->estado_pago = 'aprobado';
    expect($p->recalcularEstatus())->toBe('confirmado');

    $p->estado_logistico = 'enviado';
    expect($p->recalcularEstatus())->toBe('enviado');

    $p->estatus = 'cancelado';
    expect($p->recalcularEstatus())->toBe('cancelado');

    $q = new Pedido(['estatus' => 'pendiente_pago', 'estado_pago' => 'pendiente', 'estado_logistico' => 'pendiente', 'envio_estado' => 'por_cotizar']);
    expect($q->recalcularEstatus())->toBe('pendiente_cotizacion');
});
