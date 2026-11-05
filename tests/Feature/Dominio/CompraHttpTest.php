<?php

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => sembrarMetodosPago());

it('el carrito guarda sólo ids y cantidades en sesión', function () {
    $p = Producto::factory()->precio(350)->create();

    $this->post(route('carrito.agregar'), ['producto_id' => $p->id, 'cantidad' => 2])
        ->assertSessionHas('success')
        ->assertSessionHas('carrito', [$p->id => ['producto_id' => $p->id, 'cantidad' => 2]]);
});

it('no agrega productos no publicados ni más del stock', function () {
    $borrador = Producto::factory()->borrador()->create();
    $poco = Producto::factory()->stock(1)->create();

    $this->post(route('carrito.agregar'), ['producto_id' => $borrador->id])->assertSessionHas('error');
    $this->post(route('carrito.agregar'), ['producto_id' => $poco->id, 'cantidad' => 2])->assertSessionHas('error');
    expect(session('carrito'))->toBeNull();
});

it('comparte cantidad de items y permisos en props de Inertia', function () {
    $p = Producto::factory()->create();
    $admin = User::factory()->create(['rol' => User::ROL_VENDEDOR]);

    $this->withSession(['carrito' => [$p->id => ['producto_id' => $p->id, 'cantidad' => 3]]])
        ->actingAs($admin)
        ->get(route('carrito.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Carrito', false)
            ->where('carrito.cantidad_items', 3)
            ->where('auth.can.operar_pedidos', true)
            ->where('auth.can.revisar_pagos', false)
            ->has('tienda.nombre')
            ->where('resumen.subtotal', 3000)
            ->has('items', 1));
});

it('el checkout crea el pedido por transferencia y redirige a gracias', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->precio(500)->stock(4)->create();

    $this->actingAs($cliente)
        ->withSession(['carrito' => [$p->id => ['producto_id' => $p->id, 'cantidad' => 2]], 'checkout_token' => 'tok-1'])
        ->post(route('checkout.store'), [
            'checkout_token' => 'tok-1',
            'nombre_cliente' => 'Ana López',
            'correo_cliente' => 'ana@example.com',
            'telefono_cliente' => '7770000000',
            'metodo_pago' => 'transferencia',
            'modalidad_entrega' => 'envio_domicilio',
            'usar_direccion_existente' => false,
            'nombre_receptor' => 'Ana',
            'telefono' => '7770000000',
            'calle' => 'Morelos',
            'numero_exterior' => '5',
            'colonia' => 'Centro',
            'municipio' => 'Cuernavaca',
            'estado' => 'Morelos',
            'codigo_postal' => '62000',
            // Un cliente malicioso no puede imponer precios:
            'total' => 1,
            'precio' => 1,
        ])
        ->assertRedirect(route('checkout.gracias', Pedido::first()));

    $pedido = Pedido::first();
    expect((float) $pedido->total)->toBe(1000.0)
        ->and($pedido->metodo_pago)->toBe('transferencia')
        ->and($p->fresh()->stock)->toBe(2)
        ->and(session('carrito'))->toBeNull();

    $this->actingAs($cliente)->get(route('checkout.gracias', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/CheckoutGracias', false)
            ->where('pedido.folio', $pedido->folio)
            ->missing('openpay')
            ->has('instrucciones.banco'));
});

it('el checkout rechaza métodos de pago inactivos', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->create();

    $this->actingAs($cliente)
        ->withSession(['carrito' => [$p->id => ['producto_id' => $p->id, 'cantidad' => 1]], 'checkout_token' => 'tok-2'])
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), [
            'checkout_token' => 'tok-2',
            'nombre_cliente' => 'Ana', 'correo_cliente' => 'ana@example.com', 'telefono_cliente' => '5550000000',
            'metodo_pago' => 'efectivo_contra_entrega', 'modalidad_entrega' => 'envio_domicilio',
            'usar_direccion_existente' => false, 'nombre_receptor' => 'Ana', 'telefono' => '5550000000', 'calle' => 'X',
            'numero_exterior' => '1', 'colonia' => 'C', 'municipio' => 'M', 'estado' => 'Morelos', 'codigo_postal' => '62000',
        ])
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error');

    expect(Pedido::count())->toBe(0);
});

it('otro cliente no puede ver la página de gracias ajena', function () {
    $pedido = Pedido::factory()->create();

    $this->actingAs(clienteTienda())->get(route('checkout.gracias', $pedido))->assertForbidden();
});
