<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('el personal puede ver el dashboard y el cliente se redirige a sus pedidos', function () {
    $this->actingAs(User::factory()->create(['rol' => User::ROL_ADMIN]))
        ->get(route('dashboard'))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('cliente.pedidos.index'));
});
