<?php

require_once __DIR__.'/AdminHelpers.php';

use App\Models\Pago;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    sembrarMetodosPago();
    Storage::fake('local');
    ReglaEnvio::factory()->create(['monto' => 100]);
});

it('el admin ve el dashboard con ventas confirmadas separadas del importe de pedidos creados', function () {
    $admin = adminTienda();
    $cliente = clienteTienda();
    $producto = Producto::factory()->precio(1000)->stock(10)->create();

    opPedidoAprobado($cliente, $producto, $admin);       // 1100 aprobado
    crearPedidoCheckout($cliente, [[$producto, 2]]);     // 2100 sin pagar
    opPedidoEnRevision($cliente, $producto);             // 1100 en revisión
    Producto::factory()->pendienteAprobacion()->create();

    $this->actingAs($admin)
        ->get(route('dashboard', ['periodo' => 'hoy']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('esAdmin', true)
            ->where('periodo', 'hoy')
            ->where('comercial.ventas_confirmadas', 1100)
            ->where('comercial.importe_creados', 4300)
            ->where('comercial.pedidos_creados', 3)
            ->where('operacion.pagos_por_revisar', 1)
            ->where('operacion.pendientes_pago', 1)
            ->where('operacion.por_preparar', 1)
            ->where('catalogo.pendientes_aprobacion', 1)
            ->where('catalogo.publicados', 1)
            ->has('atencion'));
});

it('el vendedor ve sólo el tablero operativo', function () {
    $this->actingAs(vendedorTienda())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('esAdmin', false)
            ->where('comercial', null)
            ->where('catalogo', null)
            ->has('operacion.por_preparar'));
});

it('el cliente no puede entrar a nada de /admin', function (string $ruta) {
    $this->actingAs(clienteTienda())->get($ruta)->assertForbidden();
})->with([
    '/admin/pedidos',
    '/admin/pagos',
    '/admin/envios',
    '/admin/configuraciones',
    '/admin/reglas-envio',
    '/admin/usuarios',
    '/admin/cupones',
    '/admin/ofertas',
    '/admin/banners',
]);

it('el vendedor opera pedidos pero no entra a pagos, configuración ni usuarios', function () {
    $vendedor = vendedorTienda();

    $this->actingAs($vendedor)->get(route('admin.pedidos.index'))->assertOk();
    $this->actingAs($vendedor)->get(route('admin.envios.index'))->assertOk();

    foreach (['admin.pagos.index', 'admin.configuraciones.index', 'admin.usuarios.index', 'admin.cupones.index'] as $ruta) {
        $this->actingAs($vendedor)->get(route($ruta))->assertForbidden();
    }
});

it('las rutas viejas del vendedor redirigen al panel unificado', function () {
    $this->actingAs(vendedorTienda())
        ->get('/vendedor/pedidos')
        ->assertRedirect(route('admin.pedidos.index'));
});

it('el listado de pedidos busca por folio y filtra por estado de pago', function () {
    $admin = adminTienda();
    $cliente = clienteTienda();
    $producto = Producto::factory()->precio(500)->stock(10)->create();

    $revision = opPedidoEnRevision($cliente, $producto);
    crearPedidoCheckout($cliente, [[$producto, 1]]);

    $this->actingAs($admin)
        ->get(route('admin.pedidos.index', ['buscar' => $revision->folio]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Pedidos/Index')
            ->has('pedidos.data', 1)
            ->where('pedidos.data.0.folio', $revision->folio));

    $this->actingAs($admin)
        ->get(route('admin.pedidos.index', ['pago' => 'en_revision']))
        ->assertInertia(fn (Assert $page) => $page->has('pedidos.data', 1));
});

it('el detalle muestra sólo las acciones que permite el workflow según el rol', function () {
    $cliente = clienteTienda();
    $producto = Producto::factory()->precio(500)->stock(10)->create();
    $pedido = opPedidoEnRevision($cliente, $producto);

    $this->actingAs(adminTienda())
        ->get(route('admin.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Pedidos/Show')
            ->where('acciones.aprobar_pago', true)
            ->where('acciones.rechazar_pago', true)
            ->where('acciones.cancelar', true)
            ->has('pedido.pagos.0.comprobantes', 1)
            ->has('pedido.historial'));

    $this->actingAs(vendedorTienda())
        ->get(route('admin.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('acciones.aprobar_pago', false)
            ->where('acciones.cancelar', false)
            ->where('acciones.agregar_nota', true));

    expect(Pago::count())->toBe(1);
});
