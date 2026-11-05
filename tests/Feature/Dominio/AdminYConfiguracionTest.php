<?php

use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

it('define los gates del contrato por rol', function () {
    $admin = adminTienda();
    $vendedor = vendedorTienda();
    $cliente = clienteTienda();

    foreach (['gestionar-catalogo', 'gestionar-configuracion', 'gestionar-usuarios', 'revisar-pagos', 'cancelar-pedidos'] as $gate) {
        expect(Gate::forUser($admin)->allows($gate))->toBeTrue()
            ->and(Gate::forUser($vendedor)->allows($gate))->toBeFalse()
            ->and(Gate::forUser($cliente)->allows($gate))->toBeFalse();
    }

    foreach (['operar-pedidos', 'cotizar-envio'] as $gate) {
        expect(Gate::forUser($admin)->allows($gate))->toBeTrue()
            ->and(Gate::forUser($vendedor)->allows($gate))->toBeTrue()
            ->and(Gate::forUser($cliente)->allows($gate))->toBeFalse();
    }
});

it('tienda:crear-admin crea un admin con contraseña segura', function () {
    $this->artisan('tienda:crear-admin', [
        '--nombre' => 'Dueña Mr Lana',
        '--correo' => 'Duena@Example.com',
        '--password' => 'Clave-Segura-2026!',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::where('email', 'duena@example.com')->first();
    expect($user->rol)->toBe('admin')
        ->and(Hash::check('Clave-Segura-2026!', $user->password))->toBeTrue();
});

it('tienda:crear-admin rechaza contraseñas débiles y correos duplicados', function () {
    $this->artisan('tienda:crear-admin', ['--nombre' => 'A', '--correo' => 'a@example.com', '--password' => 'password', '--no-interaction' => true])
        ->assertFailed();

    User::factory()->create(['email' => 'b@example.com']);
    $this->artisan('tienda:crear-admin', ['--nombre' => 'B', '--correo' => 'b@example.com', '--password' => 'Clave-Segura-2026!', '--no-interaction' => true])
        ->assertFailed();

    expect(User::where('rol', 'admin')->count())->toBe(0);
});

it('el DatabaseSeeder no crea usuarios', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});

it('ConfiguracionTienda guarda, cachea y valida CLABE', function () {
    $config = app(ConfiguracionTienda::class);
    $config->set('contacto.whatsapp', '5217770000000');
    $config->set('home.faq', [['pregunta' => '¿Envían?', 'respuesta' => 'Sí']]);

    expect($config->publico()['contacto']['whatsapp'])->toBe('5217770000000')
        ->and($config->array('home.faq'))->toHaveCount(1)
        ->and($config->bancoConfigurado())->toBeFalse()
        ->and($config->publico())->not->toHaveKey('banco')
        ->and(ConfiguracionTienda::clabeValida('002010077777777771'))->toBeTrue()
        ->and(ConfiguracionTienda::clabeValida('002010077777777772'))->toBeFalse()
        ->and(ConfiguracionTienda::clabeValida('12345'))->toBeFalse();
});
