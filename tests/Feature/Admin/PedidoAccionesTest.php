<?php

require_once __DIR__.'/AdminHelpers.php';

use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    sembrarMetodosPago();
    Storage::fake('local');
    $this->admin = adminTienda();
    $this->cliente = clienteTienda();
    $this->producto = Producto::factory()->precio(1000)->stock(10)->create();
});

it('el admin aprueba un pago por HTTP y el pedido queda confirmado', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = opPedidoEnRevision($this->cliente, $this->producto);
    $pago = opPagoActual($pedido);

    $this->actingAs($this->admin)
        ->from(route('admin.pedidos.show', $pedido))
        ->post(route('admin.pagos.aprobar', $pago), ['notas' => 'Depósito visto en banca'])
        ->assertRedirect(route('admin.pedidos.show', $pedido))
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->estado_pago)->toBe('aprobado')
        ->and($pedido->estatus)->toBe('confirmado')
        ->and($pago->fresh()->revisado_por)->toBe($this->admin->id)
        ->and(PagoComprobante::first()->estado)->toBe('aprobado');
});

it('el vendedor no puede aprobar pagos (403)', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = opPedidoEnRevision($this->cliente, $this->producto);

    $this->actingAs(vendedorTienda())
        ->post(route('admin.pagos.aprobar', opPagoActual($pedido)))
        ->assertForbidden();

    expect($pedido->fresh()->estado_pago)->toBe('en_revision');
});

it('rechazar un pago exige motivo', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = opPedidoEnRevision($this->cliente, $this->producto);
    $pago = opPagoActual($pedido);

    $this->actingAs($this->admin)
        ->post(route('admin.pagos.rechazar', $pago), ['motivo' => ''])
        ->assertSessionHasErrors('motivo');

    expect($pedido->fresh()->estado_pago)->toBe('en_revision');

    $this->actingAs($this->admin)
        ->post(route('admin.pagos.rechazar', $pago), ['motivo' => 'El monto no coincide con el total'])
        ->assertSessionHas('success');

    expect($pedido->fresh()->estado_pago)->toBe('rechazado')
        ->and($pago->fresh()->motivo_rechazo)->toBe('El monto no coincide con el total');
});

it('una segunda aprobación devuelve error sin duplicar historial ni pagos', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = opPedidoEnRevision($this->cliente, $this->producto);
    $pago = opPagoActual($pedido);

    $this->actingAs($this->admin)->post(route('admin.pagos.aprobar', $pago))->assertSessionHas('success');
    $historial = $pedido->historial()->count();

    $this->actingAs($this->admin)
        ->post(route('admin.pagos.aprobar', $pago))
        ->assertSessionHas('error', 'El pago ya fue aprobado.');

    expect($pedido->historial()->count())->toBe($historial)
        ->and($pedido->pagos()->count())->toBe(1)
        ->and($pedido->pagos()->where('estatus', 'aprobado')->count())->toBe(1);
});

it('confirma efectivo presencial con el monto recibido', function () {
    ReglaEnvio::factory()->recoger()->create();
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]], 'efectivo_presencial', 'recoger_en_tienda');
    $pago = opPagoActual($pedido);

    $this->actingAs($this->admin)
        ->post(route('admin.pagos.confirmar-efectivo', $pago), ['monto_recibido' => 500])
        ->assertSessionHas('error');

    expect($pedido->fresh()->estado_pago)->toBe('pendiente');

    $this->actingAs($this->admin)
        ->post(route('admin.pagos.confirmar-efectivo', $pago), ['monto_recibido' => 1000])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->estado_pago)->toBe('aprobado')
        ->and((float) $pedido->saldo_pendiente)->toBe(0.0)
        ->and((float) $pago->fresh()->monto_recibido)->toBe(1000.0);
});

it('cotizar el envío actualiza el total y deja el pedido pendiente de pago', function () {
    ReglaEnvio::factory()->cotizacionManual()->create();
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);
    expect($pedido->envio_estado)->toBe('por_cotizar');

    $this->actingAs(vendedorTienda())
        ->post(route('admin.pedidos.cotizar-envio', $pedido), ['monto' => 249.5, 'nota' => 'Paquetería <b>Estafeta</b>'])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect((float) $pedido->envio)->toBe(249.5)
        ->and((float) $pedido->total)->toBe(1249.5)
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->historial()->latest('id')->first()->comentario)->not->toContain('<b>');
});

it('registra el envío con paquetería y guía, y luego confirma la entrega', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = opPedidoAprobado($this->cliente, $this->producto, $this->admin);
    $vendedor = vendedorTienda();

    $this->actingAs($vendedor)->post(route('admin.pedidos.preparacion', $pedido))->assertSessionHas('success');

    $this->actingAs($vendedor)
        ->post(route('admin.pedidos.envio', $pedido), ['paqueteria' => '', 'guia' => ''])
        ->assertSessionHasErrors(['paqueteria', 'guia']);

    $this->actingAs($vendedor)
        ->post(route('admin.pedidos.envio', $pedido), [
            'paqueteria' => 'Estafeta',
            'guia' => 'EST123456789',
            'fecha_envio' => now('America/Mexico_City')->toDateString(),
        ])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->estado_logistico)->toBe('enviado')
        ->and($pedido->estatus)->toBe('enviado')
        ->and($pedido->paqueteria)->toBe('Estafeta')
        ->and($pedido->numero_guia)->toBe('EST123456789');

    $this->actingAs($vendedor)->post(route('admin.pedidos.entrega', $pedido))->assertSessionHas('success');

    expect($pedido->fresh()->estatus)->toBe('entregado');
});

it('registra envíos en lote reportando los que no aplican', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $listo = opPedidoAprobado($this->cliente, $this->producto, $this->admin);
    app(PedidoWorkflow::class)->iniciarPreparacion($listo, $this->admin);
    $sinPagar = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    $this->actingAs(vendedorTienda())
        ->post(route('admin.envios.lote'), ['envios' => [
            ['pedido_id' => $listo->id, 'paqueteria' => 'DHL', 'guia' => 'DHL-1'],
            ['pedido_id' => $sinPagar->id, 'paqueteria' => 'DHL', 'guia' => 'DHL-2'],
        ]])
        ->assertSessionHas('success')
        ->assertSessionHas('error');

    expect($listo->fresh()->estado_logistico)->toBe('enviado')
        ->and($sinPagar->fresh()->estado_logistico)->toBe('pendiente');
});

it('cancelar exige motivo y sólo lo puede hacer el admin', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 2]]);

    $this->actingAs(vendedorTienda())->post(route('admin.pedidos.cancelar', $pedido), ['motivo' => 'Cliente lo pidió'])->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.pedidos.cancelar', $pedido), ['motivo' => ''])->assertSessionHasErrors('motivo');

    $this->actingAs($this->admin)->post(route('admin.pedidos.cancelar', $pedido), ['motivo' => 'Cliente lo pidió'])->assertSessionHas('success');

    expect($pedido->fresh()->estatus)->toBe('cancelado')
        ->and($this->producto->fresh()->stock)->toBe(10);
});

it('agrega notas internas y visibles al cliente', function () {
    ReglaEnvio::factory()->create(['monto' => 100]);
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    $this->actingAs(vendedorTienda())->post(route('admin.pedidos.notas', $pedido), ['nota' => 'Llamar antes de enviar'])->assertSessionHas('success');
    $this->actingAs(vendedorTienda())->post(route('admin.pedidos.notas', $pedido), ['nota' => 'Tu pedido sale mañana', 'visible_cliente' => true])->assertSessionHas('success');

    $notas = Pedido::find($pedido->id)->historial()->where('tipo', 'nota')->orderBy('id')->get();
    expect($notas)->toHaveCount(2)
        ->and($notas[0]->visible_cliente)->toBeFalse()
        ->and($notas[1]->visible_cliente)->toBeTrue();
});
