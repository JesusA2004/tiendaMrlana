<?php

/*
| Helpers de pruebas de dominio. Disponibles en todas las pruebas Pest (Feature y Unit).
*/

use App\Data\DatosCheckout;
use App\Models\Direccion;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\Pedidos\PedidoWorkflow;
use Database\Seeders\MetodosPagoSeeder;

function sembrarMetodosPago(): void
{
    (new MetodosPagoSeeder)->run();
}

function usuarioConRol(string $rol): User
{
    return User::factory()->create(['rol' => $rol]);
}

function adminTienda(): User
{
    return usuarioConRol(User::ROL_ADMIN);
}

function vendedorTienda(): User
{
    return usuarioConRol(User::ROL_VENDEDOR);
}

function clienteTienda(): User
{
    return usuarioConRol(User::ROL_CLIENTE);
}

/**
 * Crea un pedido real vía PedidoWorkflow::crearDesdeCheckout.
 *
 * @param  array<int, array{0: Producto, 1: int}>  $lineas  [[producto, cantidad], ...]
 */
function crearPedidoCheckout(
    User $cliente,
    array $lineas,
    string $metodo = 'transferencia',
    string $modalidad = 'envio_domicilio',
    ?Direccion $direccion = null,
    ?string $cupon = null,
): Pedido {
    if ($modalidad === 'envio_domicilio' && ! $direccion) {
        $direccion = Direccion::factory()->for($cliente)->create();
    }

    $datos = new DatosCheckout(
        lineas: collect($lineas)->map(fn (array $l) => ['producto_id' => $l[0]->id, 'cantidad' => $l[1]])->all(),
        metodoPago: $metodo,
        modalidadEntrega: $modalidad,
        direccion: $direccion,
        nombreCliente: $cliente->name,
        correoCliente: $cliente->email,
        telefonoCliente: '5550000000',
        cuponCodigo: $cupon,
    );

    return app(PedidoWorkflow::class)->crearDesdeCheckout($cliente, $datos);
}
