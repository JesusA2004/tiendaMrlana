<?php

use App\Events\Pedidos\ComprobanteRecibido;
use App\Events\Pedidos\PagoAprobado;
use App\Events\Pedidos\PagoRechazado;
use App\Events\Pedidos\PedidoCancelado;
use App\Events\Pedidos\PedidoEntregado;
use App\Events\Pedidos\PedidoEnviado;
use App\Events\Pedidos\PedidoRegistrado;
use App\Events\Pedidos\PedidoVencido;
use App\Exceptions\DominioException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\MetodoPago;
use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    sembrarMetodosPago();
    Storage::fake('local');
    ReglaEnvio::factory()->create(['monto' => 100]);
    $this->wf = app(PedidoWorkflow::class);
    $this->cliente = clienteTienda();
    $this->admin = adminTienda();
    $this->producto = Producto::factory()->precio(1000)->stock(5)->create();
});

function comprobantePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('comprobante.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

function pedidoConComprobante($test): Pedido
{
    $pedido = crearPedidoCheckout($test->cliente, [[$test->producto, 1]]);
    $test->wf->registrarComprobante($pedido, $test->cliente, comprobantePdf(), 'REF123', now()->toDateString(), 1100);

    return $pedido->fresh();
}

it('registra el pedido y despacha PedidoRegistrado', function () {
    Event::fake([PedidoRegistrado::class]);

    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect($pedido->estatus)->toBe('pendiente_pago')->and($pedido->historial)->toHaveCount(1);
    Event::assertDispatched(PedidoRegistrado::class, fn ($e) => $e->pedido->is($pedido));
});

it('rechaza un método de pago inactivo u Openpay', function (string $metodo) {
    expect(fn () => crearPedidoCheckout($this->cliente, [[$this->producto, 1]], $metodo))
        ->toThrow(TransicionInvalidaException::class, 'no está disponible');
})->with(['openpay', 'efectivo_contra_entrega', 'tarjeta']);

it('sube comprobante y pasa a revisión', function () {
    Event::fake([ComprobanteRecibido::class]);

    $pedido = pedidoConComprobante($this);
    $comprobante = PagoComprobante::first();

    expect($pedido->estado_pago)->toBe('en_revision')
        ->and($pedido->estatus)->toBe('pago_en_revision')
        ->and($comprobante->sha256)->toHaveLength(64)
        ->and($comprobante->ruta)->toStartWith("comprobantes/{$pedido->id}/");
    Storage::disk('local')->assertExists($comprobante->ruta);
    Event::assertDispatched(ComprobanteRecibido::class);
});

it('rechaza comprobantes con tipo no permitido', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect(fn () => $this->wf->registrarComprobante($pedido, $this->cliente, UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')))
        ->toThrow(DominioException::class);
    expect(PagoComprobante::count())->toBe(0);
});

it('otro usuario no puede subir comprobante', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect(fn () => $this->wf->registrarComprobante($pedido, clienteTienda(), comprobantePdf()))
        ->toThrow(AuthorizationException::class);
});

it('aprueba la transferencia y confirma el pedido', function () {
    Event::fake([PagoAprobado::class]);
    $pedido = pedidoConComprobante($this);

    $pedido = $this->wf->aprobarPago($pedido->pagos()->first(), $this->admin, 'OK banco');

    expect($pedido->estado_pago)->toBe('aprobado')
        ->and($pedido->estatus)->toBe('confirmado')
        ->and((float) $pedido->saldo_pendiente)->toBe(0.0)
        ->and($pedido->pagado_en)->not->toBeNull()
        ->and($pedido->pagos()->first()->revisado_por)->toBe($this->admin->id)
        ->and(PagoComprobante::first()->estado)->toBe('aprobado');
    Event::assertDispatched(PagoAprobado::class);
});

it('una segunda aprobación falla', function () {
    $pedido = pedidoConComprobante($this);
    $pago = $pedido->pagos()->first();
    $this->wf->aprobarPago($pago, $this->admin);

    expect(fn () => $this->wf->aprobarPago($pago->fresh(), $this->admin))
        ->toThrow(TransicionInvalidaException::class, 'ya fue aprobado');
});

it('un cliente o vendedor no puede aprobar pagos', function () {
    $pedido = pedidoConComprobante($this);
    $pago = $pedido->pagos()->first();

    expect(fn () => $this->wf->aprobarPago($pago, $this->cliente))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->wf->aprobarPago($pago, vendedorTienda()))->toThrow(AuthorizationException::class);
    expect($pedido->fresh()->estado_pago)->toBe('en_revision');
});

it('rechaza el pago con motivo y el cliente puede volver a subir', function () {
    Event::fake([PagoRechazado::class]);
    $pedido = pedidoConComprobante($this);

    expect(fn () => $this->wf->rechazarPago($pedido->pagos()->first(), $this->admin, '  '))
        ->toThrow(TransicionInvalidaException::class, 'motivo');

    $pedido = $this->wf->rechazarPago($pedido->pagos()->first(), $this->admin, 'Monto no coincide');

    expect($pedido->estado_pago)->toBe('rechazado')
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->pagos()->first()->motivo_rechazo)->toBe('Monto no coincide');
    Event::assertDispatched(PagoRechazado::class, fn ($e) => $e->motivo === 'Monto no coincide');

    $this->wf->registrarComprobante($pedido, $this->cliente, comprobantePdf());
    expect($pedido->fresh()->estado_pago)->toBe('en_revision')->and(PagoComprobante::count())->toBe(2);
});

it('confirma efectivo presencial con monto suficiente', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]], 'efectivo_presencial');
    $pago = $pedido->pagos()->first();

    expect(fn () => $this->wf->confirmarEfectivo($pago, $this->admin, 10))
        ->toThrow(TransicionInvalidaException::class, 'menor al saldo');

    $pedido = $this->wf->confirmarEfectivo($pago, $this->admin, 1100);

    expect($pedido->estado_pago)->toBe('aprobado')
        ->and((float) $pedido->pagos()->first()->monto_recibido)->toBe(1100.0);
});

it('no permite preparar sin pago aprobado', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect(fn () => $this->wf->iniciarPreparacion($pedido, vendedorTienda()))
        ->toThrow(TransicionInvalidaException::class, 'pago aún no está aprobado');
});

it('contra entrega permite preparar sin pago y exige efectivo al entregar', function () {
    MetodoPago::where('clave', 'efectivo_contra_entrega')->update(['activo' => true]);
    ReglaEnvio::query()->update(['permite_contra_entrega' => true]);
    $vendedor = vendedorTienda();

    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]], 'efectivo_contra_entrega');
    expect($pedido->estatus)->toBe('confirmado')->and($pedido->estado_pago)->toBe('contra_entrega');

    $this->wf->iniciarPreparacion($pedido, $vendedor);
    $this->wf->registrarEnvio($pedido, $vendedor, 'Estafeta', 'GUIA-1');

    expect(fn () => $this->wf->confirmarEntrega($pedido, $this->admin))
        ->toThrow(TransicionInvalidaException::class, 'efectivo');

    $pedido = $this->wf->confirmarEntrega($pedido, $this->admin, 1100);

    expect($pedido->estado_logistico)->toBe('entregado')
        ->and($pedido->estado_pago)->toBe('aprobado')
        ->and($pedido->estatus)->toBe('entregado');
});

it('contra entrega se rechaza si la regla de envío no lo permite', function () {
    MetodoPago::where('clave', 'efectivo_contra_entrega')->update(['activo' => true]);

    expect(fn () => crearPedidoCheckout($this->cliente, [[$this->producto, 1]], 'efectivo_contra_entrega'))
        ->toThrow(TransicionInvalidaException::class, 'contra entrega no está disponible');
});

it('flujo logístico completo: preparación, envío con guía y entrega', function () {
    Event::fake([PedidoEnviado::class, PedidoEntregado::class]);
    $vendedor = vendedorTienda();
    $pedido = pedidoConComprobante($this);
    $this->wf->aprobarPago($pedido->pagos()->first(), $this->admin);

    $this->wf->iniciarPreparacion($pedido, $vendedor);
    $this->wf->marcarListoEnvio($pedido, $vendedor);

    expect(fn () => $this->wf->registrarEnvio($pedido, $vendedor, 'DHL', ''))
        ->toThrow(TransicionInvalidaException::class, 'guía');

    $pedido = $this->wf->registrarEnvio($pedido, $vendedor, 'DHL', '123456789', '2026-11-05');
    expect($pedido->estatus)->toBe('enviado')
        ->and($pedido->numero_guia)->toBe('123456789')
        ->and($pedido->fecha_envio->toDateString())->toBe('2026-11-05');

    expect(fn () => $this->wf->cancelar($pedido, $this->admin, 'tarde'))
        ->toThrow(TransicionInvalidaException::class, 'ya fue enviado');

    $pedido = $this->wf->confirmarEntrega($pedido, $vendedor);
    expect($pedido->estatus)->toBe('entregado')->and($pedido->entregado_en)->not->toBeNull();

    Event::assertDispatched(PedidoEnviado::class);
    Event::assertDispatched(PedidoEntregado::class);
    expect($pedido->historial()->count())->toBeGreaterThanOrEqual(6);
});

it('el vendedor no puede cancelar pedidos', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect(fn () => $this->wf->cancelar($pedido, vendedorTienda(), 'x'))->toThrow(AuthorizationException::class);
});

it('cancelar un pedido pagado marca reembolso pendiente', function () {
    Event::fake([PedidoCancelado::class]);
    $pedido = pedidoConComprobante($this);
    $this->wf->aprobarPago($pedido->pagos()->first(), $this->admin);

    $pedido = $this->wf->cancelar($pedido, $this->admin, 'Sin existencia física');

    expect($pedido->estatus)->toBe('cancelado')
        ->and($pedido->estado_pago)->toBe('aprobado')
        ->and($pedido->historial()->where('datos->reembolso_pendiente', true)->exists())->toBeTrue()
        ->and($this->producto->fresh()->stock)->toBe(5);
    Event::assertDispatched(PedidoCancelado::class, fn ($e) => $e->requiereReembolso);

    $pedido = $this->wf->registrarReembolso($pedido, $this->admin, 'SPEI devuelto');
    expect($pedido->estatus)->toBe('reembolsado')->and($pedido->estado_pago)->toBe('reembolsado');
});

it('el comando pedidos:vencer vence pedidos sin pago y libera stock', function () {
    Event::fake([PedidoVencido::class]);
    $vencible = crearPedidoCheckout($this->cliente, [[$this->producto, 2]]);
    $vigente = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);
    $enRevision = pedidoConComprobante($this);
    Pedido::whereKey([$vencible->id, $enRevision->id])->update(['vence_en' => now()->subHour()]);

    expect($this->producto->fresh()->stock)->toBe(1);

    $this->artisan('pedidos:vencer')->assertSuccessful();

    expect($vencible->fresh()->estatus)->toBe('vencido')
        ->and($vencible->fresh()->vencido_en)->not->toBeNull()
        ->and($vigente->fresh()->estatus)->toBe('pendiente_pago')
        ->and($enRevision->fresh()->estatus)->toBe('pago_en_revision')
        ->and($this->producto->fresh()->stock)->toBe(3);
    Event::assertDispatchedTimes(PedidoVencido::class, 1);
});

it('las transiciones inválidas lanzan excepción legible', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]]);

    expect(fn () => $this->wf->registrarEnvio($pedido, $this->admin, 'DHL', '1'))
        ->toThrow(TransicionInvalidaException::class)
        ->and(fn () => $this->wf->confirmarEntrega($pedido, $this->admin))
        ->toThrow(TransicionInvalidaException::class)
        ->and(fn () => $this->wf->cotizarEnvio($pedido, 10, $this->admin))
        ->toThrow(TransicionInvalidaException::class, 'no está pendiente de cotización');
});
