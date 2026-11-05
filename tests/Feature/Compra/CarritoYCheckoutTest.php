<?php

use App\Models\Cupon;
use App\Models\Direccion;
use App\Models\MetodoPago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => sembrarMetodosPago());

function datosCheckout(array $extra = []): array
{
    return [
        'checkout_token' => 'tok-compra',
        'nombre_cliente' => 'Ana López',
        'correo_cliente' => 'ana@example.com',
        'telefono_cliente' => '7771234567',
        'metodo_pago' => 'transferencia',
        'modalidad_entrega' => 'envio_domicilio',
        'usar_direccion_existente' => false,
        'nombre_receptor' => 'Ana López',
        'telefono' => '7771234567',
        'calle' => 'Morelos',
        'numero_exterior' => '5',
        'colonia' => 'Centro',
        'municipio' => 'Cuernavaca',
        'estado' => 'Morelos',
        'codigo_postal' => '62000',
        ...$extra,
    ];
}

function sesionCarrito(Producto $p, int $cantidad, array $extra = []): array
{
    return ['carrito' => [$p->id => ['producto_id' => $p->id, 'cantidad' => $cantidad]], 'checkout_token' => 'tok-compra', ...$extra];
}

// ------------------------------------------------------------------ carrito

it('agrega al carrito y muestra la línea con su precio', function () {
    $p = Producto::factory()->precio(420)->stock(5)->create();

    $this->post(route('carrito.agregar'), ['producto_id' => $p->id, 'cantidad' => 2])
        ->assertSessionHas('success');

    $this->get(route('carrito.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Carrito')
            ->has('items', 1)
            ->where('items.0.cantidad', 2)
            ->where('items.0.precio', 420)
            ->where('resumen.subtotal', 840)
            ->where('resumen.envio_por_cotizar', true)
            ->has('tienda.enlaces'));
});

it('acepta productos bajo pedido aunque no tengan stock (máximo 99)', function () {
    $p = Producto::factory()->bajoPedido(10)->stock(0)->create();

    $this->post(route('carrito.agregar'), ['producto_id' => $p->id, 'cantidad' => 3])->assertSessionHas('success');
    $this->post(route('carrito.agregar'), ['producto_id' => $p->id, 'cantidad' => 100])->assertSessionHasErrors('cantidad');

    expect(session('carrito')[$p->id]['cantidad'])->toBe(3);
});

it('actualiza la cantidad y la limita al stock', function () {
    $p = Producto::factory()->stock(3)->create();

    $this->withSession(sesionCarrito($p, 1))
        ->patch(route('carrito.actualizar', $p), ['cantidad' => 3])
        ->assertSessionHas('success');
    expect(session('carrito')[$p->id]['cantidad'])->toBe(3);

    $this->patch(route('carrito.actualizar', $p), ['cantidad' => 4])->assertSessionHas('error');
    expect(session('carrito')[$p->id]['cantidad'])->toBe(3);

    $this->patch(route('carrito.actualizar', $p), ['cantidad' => 0]);
    expect(session('carrito'))->toBeEmpty();
});

it('aplica un cupón válido y rechaza uno inválido con mensaje junto al campo', function () {
    $p = Producto::factory()->precio(1000)->create();
    Cupon::factory()->create(['codigo' => 'BIENVENIDA', 'valor' => 10]);
    Cupon::factory()->vencido()->create(['codigo' => 'VIEJO']);

    $this->withSession(sesionCarrito($p, 1))
        ->post(route('carrito.cupon.aplicar'), ['codigo' => 'bienvenida'])
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $this->get(route('carrito.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cupon.codigo', 'BIENVENIDA')
            ->where('resumen.descuento_cupon', 100)
            ->where('resumen.total', 900));

    $this->post(route('carrito.cupon.aplicar'), ['codigo' => 'VIEJO'])->assertSessionHasErrors('codigo');
    $this->post(route('carrito.cupon.aplicar'), ['codigo' => 'NOEXISTE'])->assertSessionHasErrors('codigo');
    $this->post(route('carrito.cupon.aplicar'), ['codigo' => ''])->assertSessionHasErrors('codigo');

    $this->delete(route('carrito.cupon.quitar'))->assertSessionHas('success');
    expect(session('carrito_cupon'))->toBeNull();
});

// ------------------------------------------------------------------ checkout

it('el checkout exige sesión verificada', function () {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
});

it('muestra el checkout con token, métodos sin tarjeta y envío cotizado en vivo', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->precio(500)->create();
    ReglaEnvio::factory()->create(['monto' => 180, 'estados' => ['Morelos']]);
    $dir = Direccion::factory()->for($cliente)->create(['estado' => 'Morelos', 'codigo_postal' => '62000']);

    $this->actingAs($cliente)
        ->withSession(['carrito' => [$p->id => ['producto_id' => $p->id, 'cantidad' => 1]]])
        ->get(route('checkout.index', ['direccion_id' => $dir->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Checkout')
            ->has('checkout_token')
            ->where('resumen.envio', 180)
            ->where('resumen.total', 680)
            ->where('seleccion.direccion_id', $dir->id)
            ->where('metodos_pago', fn ($metodos) => collect($metodos)->pluck('clave')->diff(['transferencia', 'efectivo_presencial', 'efectivo_contra_entrega'])->isEmpty()));

    // Dirección nueva (aún sin guardar): se cotiza por estado; otro estado queda por cotizar.
    $this->actingAs($cliente)
        ->get(route('checkout.index', ['nueva' => 1, 'estado' => 'Jalisco', 'codigo_postal' => '44100']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seleccion.nueva', true)
            ->where('resumen.envio_por_cotizar', true));
});

it('crea el pedido con total correcto (regla de tarifa fija) por transferencia', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->precio(750)->stock(5)->create();
    ReglaEnvio::factory()->create(['monto' => 150]);

    $this->actingAs($cliente)
        ->withSession(sesionCarrito($p, 2))
        ->post(route('checkout.store'), datosCheckout(['total' => 1, 'envio' => 0]))
        ->assertRedirect(route('checkout.gracias', Pedido::first()));

    $pedido = Pedido::first();
    expect((float) $pedido->subtotal)->toBe(1500.0)
        ->and((float) $pedido->envio)->toBe(150.0)
        ->and((float) $pedido->total)->toBe(1650.0)
        ->and($pedido->metodo_pago)->toBe('transferencia')
        ->and($pedido->envio_estado)->toBe('calculado')
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->vence_en)->not->toBeNull()
        ->and($pedido->direccion->user_id)->toBe($cliente->id)
        ->and($p->fresh()->stock)->toBe(3)
        ->and(session('carrito'))->toBeNull();
});

it('no reserva stock para productos bajo pedido', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->bajoPedido(12)->stock(0)->create();

    $this->actingAs($cliente)
        ->withSession(sesionCarrito($p, 4))
        ->post(route('checkout.store'), datosCheckout())
        ->assertRedirect();

    expect(Pedido::count())->toBe(1)->and($p->fresh()->stock)->toBe(0);
});

it('un doble envío del formulario no duplica el pedido', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->stock(5)->create();

    $this->actingAs($cliente)->withSession(sesionCarrito($p, 1))
        ->post(route('checkout.store'), datosCheckout())
        ->assertRedirect();
    $pedido = Pedido::sole();

    // Mismo token otra vez (doble clic / reenvío): regresa al mismo pedido.
    $this->actingAs($cliente)->withSession(sesionCarrito($p, 1, ['checkout_token' => 'otro']))
        ->post(route('checkout.store'), datosCheckout())
        ->assertRedirect(route('checkout.gracias', $pedido));

    expect(Pedido::count())->toBe(1);
});

it('rechaza un token de checkout que no corresponde a la sesión', function () {
    $p = Producto::factory()->create();

    $this->actingAs(clienteTienda())
        ->withSession(sesionCarrito($p, 1, ['checkout_token' => 'vigente']))
        ->post(route('checkout.store'), datosCheckout(['checkout_token' => 'viejo']))
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error');

    expect(Pedido::count())->toBe(0);
});

it('rechaza el checkout de un producto sin stock', function () {
    $cliente = clienteTienda();
    $p = Producto::factory()->stock(1)->create();

    $this->actingAs($cliente)
        ->withSession(sesionCarrito($p, 3))
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), datosCheckout())
        ->assertRedirect(route('checkout.index'))
        ->assertSessionHas('error');

    expect(Pedido::count())->toBe(0)->and($p->fresh()->stock)->toBe(1);
});

it('rechaza métodos inactivos y Openpay', function (string $metodo, string $tipoError) {
    $cliente = clienteTienda();
    $p = Producto::factory()->create();
    MetodoPago::where('clave', 'efectivo_presencial')->update(['activo' => false]);

    $respuesta = $this->actingAs($cliente)
        ->withSession(sesionCarrito($p, 1))
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), datosCheckout(['metodo_pago' => $metodo]))
        ->assertRedirect(route('checkout.index'));

    $tipoError === 'validacion'
        ? $respuesta->assertSessionHasErrors('metodo_pago')
        : $respuesta->assertSessionHas('error');

    expect(Pedido::count())->toBe(0);
})->with([
    'openpay' => ['openpay', 'validacion'],
    'tarjeta' => ['tarjeta', 'validacion'],
    'efectivo presencial inactivo' => ['efectivo_presencial', 'dominio'],
    'contra entrega inactivo' => ['efectivo_contra_entrega', 'dominio'],
]);

it('no existe ninguna ruta de pago con tarjeta', function () {
    expect(Route::has('checkout.pagar'))->toBeFalse();

    $pedido = Pedido::factory()->create();
    $this->actingAs($pedido->user ?? clienteTienda())
        ->post('/checkout/gracias/'.$pedido->id.'/pagar', ['token_id' => 'x'])
        ->assertNotFound();
});

it('recoger en tienda no exige dirección', function () {
    ConfiguracionTienda::class;
    app(ConfiguracionTienda::class)->set('recoger.direccion', 'Av. Siempre Viva 1, Cuernavaca');
    $cliente = clienteTienda();
    $p = Producto::factory()->precio(300)->create();

    $this->actingAs($cliente)
        ->withSession(sesionCarrito($p, 1))
        ->post(route('checkout.store'), [
            'checkout_token' => 'tok-compra',
            'nombre_cliente' => 'Ana', 'correo_cliente' => 'ana@example.com', 'telefono_cliente' => '7771234567',
            'metodo_pago' => 'efectivo_presencial', 'modalidad_entrega' => 'recoger_en_tienda',
        ])
        ->assertRedirect();

    $pedido = Pedido::sole();
    expect($pedido->direccion_id)->toBeNull()
        ->and((float) $pedido->total)->toBe(300.0)
        ->and($pedido->modalidad_entrega)->toBe('recoger_en_tienda');
});
