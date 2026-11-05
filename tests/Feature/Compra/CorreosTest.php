<?php

use App\Events\Pedidos\PagoAprobado;
use App\Events\Pedidos\PedidoEnviado;
use App\Events\Pedidos\PedidoRegistrado;
use App\Mail\AdminCreatedUserMail;
use App\Mail\BienvenidaMail;
use App\Mail\Pedidos\AvisoStaffPedidoNuevoMail;
use App\Mail\Pedidos\InstruccionesTransferenciaMail;
use App\Mail\Pedidos\PagoAprobadoMail;
use App\Mail\Pedidos\PedidoEnviadoMail;
use App\Mail\Pedidos\PedidoRegistradoMail;
use App\Models\Cupon;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => sembrarMetodosPago());

it('al registrar un pedido encola los correos una sola vez aunque el evento se repita', function () {
    Mail::fake();
    ReglaEnvio::factory()->create(['monto' => 99]);
    $admin = adminTienda();
    $cliente = clienteTienda();

    $pedido = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]]);

    // Segundo disparo del mismo evento (reintento / doble despacho).
    event(new PedidoRegistrado($pedido, $cliente));

    Mail::assertQueued(PedidoRegistradoMail::class, 1);
    Mail::assertQueued(InstruccionesTransferenciaMail::class, 1);
    Mail::assertQueued(AvisoStaffPedidoNuevoMail::class, fn ($m) => $m->hasTo($admin->email));
    Mail::assertQueued(AvisoStaffPedidoNuevoMail::class, 1);
    Mail::assertQueued(PedidoRegistradoMail::class, fn ($m) => $m->hasTo($cliente->email));
});

it('con envío por cotizar no envía instrucciones de transferencia todavía', function () {
    Mail::fake();
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]]);

    expect($pedido->envio_estado)->toBe('por_cotizar');
    Mail::assertQueued(PedidoRegistradoMail::class, 1);
    Mail::assertNotQueued(InstruccionesTransferenciaMail::class);
});

it('el aviso al staff usa el correo de contacto configurado', function () {
    Mail::fake();
    app(ConfiguracionTienda::class)->set('contacto.correo', 'ventas@mrlana.test');
    adminTienda();

    crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]]);

    Mail::assertQueued(AvisoStaffPedidoNuevoMail::class, fn ($m) => $m->hasTo('ventas@mrlana.test'));
});

it('los correos de pago aprobado y envío se deduplican', function () {
    Mail::fake();
    ReglaEnvio::factory()->create(['monto' => 99]);
    $cliente = clienteTienda();
    $pedido = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]]);
    $pago = $pedido->pagos()->first();

    event(new PagoAprobado($pedido, $pago));
    event(new PagoAprobado($pedido, $pago));
    event(new PedidoEnviado($pedido));
    event(new PedidoEnviado($pedido));

    Mail::assertQueued(PagoAprobadoMail::class, 1);
    Mail::assertQueued(PedidoEnviadoMail::class, 1);
});

it('los correos de pedido van en cola y después del commit', function () {
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]]);
    $correo = new PedidoRegistradoMail($pedido);

    expect($correo)->toBeInstanceOf(ShouldQueue::class)
        ->and($correo->afterCommit)->toBeTrue();
});

it('los correos renderizan con datos bancarios sólo si están configurados', function () {
    ReglaEnvio::factory()->create(['monto' => 99]);
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]]);

    $html = (new InstruccionesTransferenciaMail($pedido))->render();
    expect($html)->toContain($pedido->folio)
        ->toContain('Te contactaremos con los datos bancarios');

    app(ConfiguracionTienda::class)->setMuchos([
        'banco.nombre' => 'BBVA',
        'banco.beneficiario' => 'Mr Lana SA de CV',
        'banco.clabe' => '012180001234567899',
    ]);

    $html = (new InstruccionesTransferenciaMail($pedido->fresh()))->render();
    expect($html)->toContain('BBVA')->toContain('0121 8000 1234 5678 99')->toContain($pedido->folio);
});

it('envía la bienvenida al registrarse, sin contraseña', function () {
    Mail::fake();

    $this->post(route('register.store'), [
        'name' => 'Nuevo Cliente',
        'email' => 'nuevo@example.com',
        'password' => 'Secreta-12345',
        'password_confirmation' => 'Secreta-12345',
    ]);

    $user = User::where('email', 'nuevo@example.com')->firstOrFail();
    Mail::assertQueued(BienvenidaMail::class, fn ($m) => $m->hasTo('nuevo@example.com'));

    $html = (new BienvenidaMail($user))->render();
    expect($html)->toContain('Nuevo Cliente')->not->toContain('Secreta-12345');
});

it('el listener de bienvenida responde al evento Registered', function () {
    Mail::fake();
    $user = clienteTienda();

    event(new Registered($user));

    Mail::assertQueued(BienvenidaMail::class, 1);
});

it('el correo de cuenta creada por admin no incluye la contraseña', function () {
    $html = (new AdminCreatedUserMail('Ana', 'ana@example.com', 'ClaveTemporal99', 'https://tienda.test/reset/abc', 'vendedor'))->render();

    expect($html)->not->toContain('ClaveTemporal99')
        ->toContain('https://tienda.test/reset/abc')
        ->toContain('Vendedor');
});

it('las plantillas reciben el pedido presentado (no el modelo): método, entrega, cupón y envío por cotizar', function () {
    Cupon::factory()->create(['codigo' => 'QA10', 'tipo' => 'porcentaje', 'valor' => 10]);
    $pedido = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->precio(1000)->create(), 1]], cupon: 'QA10');

    expect($pedido->envio_estado)->toBe('por_cotizar');

    $html = (new PedidoRegistradoMail($pedido))->render();
    expect($html)->toContain('Transferencia bancaria')
        ->toContain('Envío a domicilio')
        ->toContain('Por cotizar')
        ->toContain('QA10')
        ->toContain('−$100.00');

    $staff = (new AvisoStaffPedidoNuevoMail($pedido))->render();
    expect($staff)->toContain('Nuevo pedido recibido')
        ->toContain($pedido->folio)
        ->toContain('Envío por cotizar');
});
