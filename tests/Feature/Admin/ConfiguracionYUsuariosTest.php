<?php

require_once __DIR__.'/AdminHelpers.php';

use App\Models\Cupon;
use App\Models\MetodoPago;
use App\Models\PagoComprobante;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    sembrarMetodosPago();
    $this->admin = adminTienda();
});

it('la configuración bancaria rechaza una CLABE inválida y acepta una válida', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.configuraciones.update', 'banco'), [
            'nombre' => 'BBVA',
            'beneficiario' => 'Mr Lana SA de CV',
            'clabe' => '002010077777777772',
        ])
        ->assertSessionHasErrors('clabe');

    expect(app(ConfiguracionTienda::class)->banco()['clabe'])->toBeNull();

    $this->actingAs($this->admin)
        ->put(route('admin.configuraciones.update', 'banco'), [
            'nombre' => 'BBVA',
            'beneficiario' => 'Mr Lana SA de CV',
            'clabe' => '0020 1007 7777 7777 71',
        ])
        ->assertSessionHasNoErrors();

    $config = app(ConfiguracionTienda::class);
    $config->olvidar();
    expect($config->banco()['clabe'])->toBe('002010077777777771')
        ->and($config->bancoConfigurado())->toBeTrue();
});

it('la configuración guarda texto plano sin HTML', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.configuraciones.update', 'politicas'), [
            'envios' => "<script>alert(1)</script>Enviamos en 3 días.\nSin costo extra.",
        ])
        ->assertSessionHasNoErrors();

    $texto = app(ConfiguracionTienda::class)->politicas()['envios'];
    expect($texto)->not->toContain('<script>')->and($texto)->toContain("\n");

    $this->actingAs($this->admin)
        ->put(route('admin.configuraciones.update', 'home'), [
            'faq' => [['pregunta' => '¿Hacen envíos?', 'respuesta' => 'Sí, a todo México.']],
            'beneficios' => [['titulo' => 'Envío seguro', 'descripcion' => 'Empaque reforzado', 'icono' => 'truck']],
        ])
        ->assertSessionHasNoErrors();

    expect(app(ConfiguracionTienda::class)->array('home.faq'))->toHaveCount(1);
});

it('openpay no se puede activar desde el panel', function () {
    $openpay = MetodoPago::where('clave', 'openpay')->first();

    $this->actingAs($this->admin)
        ->put(route('admin.metodos-pago.update', $openpay), [
            'nombre' => 'Tarjeta', 'clave' => 'openpay', 'activo' => true,
        ])
        ->assertSessionHasErrors('activo');

    expect($openpay->fresh()->activo)->toBeFalse();
});

it('configura contra entrega con estados y monto máximo', function () {
    $ce = MetodoPago::where('clave', 'efectivo_contra_entrega')->first();

    $this->actingAs($this->admin)
        ->put(route('admin.metodos-pago.update', $ce), [
            'nombre' => $ce->nombre, 'clave' => $ce->clave, 'activo' => true,
            'configuracion' => ['estados' => ['Morelos', 'Ciudad de México'], 'monto_maximo' => 5000],
        ])
        ->assertSessionHasNoErrors();

    $ce->refresh();
    expect($ce->activo)->toBeTrue()
        ->and($ce->config('estados'))->toBe(['Morelos', 'Ciudad de México'])
        ->and((float) $ce->config('monto_maximo'))->toBe(5000.0);
});

it('valida las reglas de envío', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.reglas-envio.store'), ['nombre' => 'Local', 'tipo' => 'tarifa_fija', 'prioridad' => 1, 'cp_prefijos' => ['62a']])
        ->assertSessionHasErrors(['monto', 'cp_prefijos.0']);

    $this->actingAs($this->admin)
        ->post(route('admin.reglas-envio.store'), [
            'nombre' => 'Morelos', 'tipo' => 'tarifa_fija', 'monto' => 120, 'gratis_desde' => 3000,
            'estados' => ['Morelos'], 'cp_prefijos' => ['62'], 'prioridad' => 10, 'activo' => true, 'permite_contra_entrega' => true,
        ])
        ->assertSessionHasNoErrors();

    $regla = ReglaEnvio::first();
    expect((float) $regla->monto)->toBe(120.0)
        ->and($regla->estados)->toBe(['Morelos'])
        ->and($regla->permite_contra_entrega)->toBeTrue();
});

it('al crear un usuario se envía enlace de restablecimiento, nunca contraseña', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.usuarios.store'), ['name' => 'Vendedora', 'email' => 'Vende@Example.com', 'rol' => 'vendedor'])
        ->assertSessionHas('success');

    $user = User::where('email', 'vende@example.com')->firstOrFail();
    expect($user->rol)->toBe('vendedor');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('no se puede eliminar ni degradar al último admin, ni a uno mismo', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.usuarios.destroy', $this->admin))
        ->assertSessionHas('error');

    $this->actingAs($this->admin)
        ->put(route('admin.usuarios.update', $this->admin), ['name' => 'Yo', 'email' => $this->admin->email, 'rol' => 'cliente'])
        ->assertSessionHasErrors('rol');

    expect($this->admin->fresh())->not->toBeNull()->and($this->admin->fresh()->rol)->toBe('admin');

    // Con un segundo admin sí se puede eliminar al otro, pero nunca dejar cero.
    $otro = adminTienda();
    $this->actingAs($this->admin)->delete(route('admin.usuarios.destroy', $otro))->assertSessionHas('success');
    expect(User::where('rol', 'admin')->count())->toBe(1);
});

it('la descarga de comprobantes está protegida', function () {
    Storage::fake('local');
    ReglaEnvio::factory()->create(['monto' => 100]);
    $cliente = clienteTienda();
    $pedido = opPedidoEnRevision($cliente, Producto::factory()->precio(500)->stock(5)->create());
    $comprobante = PagoComprobante::firstOrFail();

    $this->get(route('admin.comprobantes.show', $comprobante))->assertRedirect(route('login'));
    $this->actingAs($cliente)->get(route('admin.comprobantes.show', $comprobante))->assertForbidden();
    $this->actingAs(clienteTienda())->get(route('admin.comprobantes.descargar', $comprobante))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.comprobantes.show', $comprobante))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(vendedorTienda())
        ->get(route('admin.comprobantes.descargar', $comprobante))
        ->assertOk()
        ->assertDownload('comprobante-'.$pedido->folio.'-'.$comprobante->id.'.pdf');
});

it('valida cupones: porcentaje 1–100 y códigos únicos en mayúsculas', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.cupones.store'), ['codigo' => 'promo', 'nombre' => 'Promo', 'tipo' => 'porcentaje', 'valor' => 150])
        ->assertSessionHasErrors('valor');

    $this->actingAs($this->admin)
        ->post(route('admin.cupones.store'), ['codigo' => 'promo 10', 'nombre' => 'Promo', 'tipo' => 'porcentaje', 'valor' => 10, 'usos_maximos' => 50])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->post(route('admin.cupones.store'), ['codigo' => 'PROMO10', 'nombre' => 'Otra', 'tipo' => 'monto_fijo', 'valor' => 100])
        ->assertSessionHasErrors('codigo');

    expect(Cupon::first()->codigo)->toBe('PROMO10');
});

it('la sección negocio precarga el nombre efectivo de la tienda aunque no se haya guardado', function () {
    expect(app(ConfiguracionTienda::class)->string('tienda.nombre'))->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('admin.configuraciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('valores.negocio.nombre', config('app.name')));
});
