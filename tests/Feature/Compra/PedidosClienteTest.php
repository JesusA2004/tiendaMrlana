<?php

use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pdf\PdfNoDisponibleException;
use App\Services\Pdf\PdfService;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    sembrarMetodosPago();
    Storage::fake('local');
});

function pedidoTransferencia(?User $cliente = null): Pedido
{
    $cliente ??= clienteTienda();
    ReglaEnvio::factory()->create(['monto' => 100]);

    return crearPedidoCheckout($cliente, [[Producto::factory()->precio(500)->create(), 1]]);
}

class PdfServiceFake extends PdfService
{
    /** @var list<array{vista: string, data: array}> */
    public array $generados = [];

    public bool $fallar = false;

    public function desdeVista(string $vista, array $data = []): string
    {
        if ($this->fallar) {
            throw new PdfNoDisponibleException('No pudimos generar el PDF en este momento. Inténtalo más tarde o contáctanos.');
        }

        view($vista, $data)->render(); // la vista debe renderizar sin errores
        $this->generados[] = ['vista' => $vista, 'data' => $data];

        return "%PDF-1.4\n%fake";
    }
}

// ------------------------------------------------------------------ gracias / instrucciones

it('la página del pedido muestra datos bancarios configurados sólo al dueño', function () {
    $config = app(ConfiguracionTienda::class);
    $config->setMuchos([
        'banco.nombre' => 'BBVA',
        'banco.beneficiario' => 'Mr Lana SA de CV',
        'banco.clabe' => '012180001234567899',
    ]);
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)->get(route('checkout.gracias', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/CheckoutGracias')
            ->where('pedido.folio', $pedido->folio)
            ->where('instrucciones.banco.configurado', true)
            ->where('instrucciones.banco.clabe', '012180001234567899')
            ->where('instrucciones.banco.referencia', $pedido->folio)
            ->where('instrucciones.puede_subir_comprobante', true)
            ->missing('openpay'));

    // Staff ve el pedido pero no los datos bancarios ni el formulario de comprobante.
    $this->actingAs(vendedorTienda())->get(route('checkout.gracias', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrucciones.banco', null)
            ->where('instrucciones.puede_subir_comprobante', false));
});

it('si el banco no está configurado no inventa datos', function () {
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)->get(route('checkout.gracias', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrucciones.banco', ['configurado' => false]));
});

it('con envío por cotizar no muestra datos bancarios ni permite subir comprobante', function () {
    $cliente = clienteTienda();
    $pedido = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]]);

    expect($pedido->envio_estado)->toBe('por_cotizar');

    $this->actingAs($cliente)->get(route('checkout.gracias', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.envio_por_cotizar', true)
            ->where('instrucciones.banco', null)
            ->where('instrucciones.puede_subir_comprobante', false));
});

// ------------------------------------------------------------------ comprobantes

it('el dueño sube un comprobante y el pago pasa a revisión', function () {
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)
        ->from(route('checkout.gracias', $pedido))
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), [
            'archivo' => UploadedFile::fake()->image('comprobante.jpg', 600, 800)->size(300),
            'referencia' => 'SPEI 123',
            'fecha_transferencia' => now()->toDateString(),
            'monto_declarado' => '600.00',
        ])
        ->assertRedirect(route('checkout.gracias', $pedido))
        ->assertSessionHas('success');

    $comprobante = PagoComprobante::sole();
    expect($pedido->fresh()->estado_pago)->toBe('en_revision')
        ->and($comprobante->referencia)->toBe('SPEI 123')
        ->and((float) $comprobante->monto_declarado)->toBe(600.0);
    Storage::disk('local')->assertExists($comprobante->ruta);
    expect($comprobante->ruta)->toStartWith('comprobantes/'.$pedido->id.'/');
});

it('rechaza comprobantes con tipo inválido o de más de 5 MB', function () {
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), [
            'archivo' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('archivo');

    $this->actingAs($pedido->user)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), [
            'archivo' => UploadedFile::fake()->create('grande.pdf', 6000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('archivo');

    $this->actingAs($pedido->user)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), [
            'archivo' => UploadedFile::fake()->image('ok.png'),
            'fecha_transferencia' => now()->addDays(3)->toDateString(),
        ])
        ->assertSessionHasErrors('fecha_transferencia');

    expect(PagoComprobante::count())->toBe(0)
        ->and($pedido->fresh()->estado_pago)->toBe('pendiente');
});

it('otro cliente no puede subir ni descargar comprobantes ajenos', function () {
    $pedido = pedidoTransferencia();
    $intruso = clienteTienda();

    $this->actingAs($intruso)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), ['archivo' => UploadedFile::fake()->image('x.jpg')])
        ->assertForbidden();

    $comprobante = app(PedidoWorkflow::class)
        ->registrarComprobante($pedido, $pedido->user, UploadedFile::fake()->image('c.png'));

    $this->actingAs($intruso)
        ->get(route('cliente.pedidos.comprobantes.descargar', [$pedido, $comprobante]))
        ->assertForbidden();

    // Dueño y staff sí; nombre de descarga seguro (sin el nombre original).
    $this->actingAs($pedido->user)
        ->get(route('cliente.pedidos.comprobantes.descargar', [$pedido, $comprobante]))
        ->assertOk()
        ->assertDownload('comprobante-'.Str::slug($pedido->folio).'-'.$comprobante->id.'.png');

    $this->actingAs(adminTienda())
        ->get(route('cliente.pedidos.comprobantes.descargar', [$pedido, $comprobante]))
        ->assertOk();

    // Comprobante de otro pedido por la URL de éste → 404.
    $otro = pedidoTransferencia($pedido->user);
    $this->actingAs($pedido->user)
        ->get(route('cliente.pedidos.comprobantes.descargar', [$otro, $comprobante]))
        ->assertNotFound();
});

it('muestra el motivo de rechazo y permite volver a subir', function () {
    $pedido = pedidoTransferencia();
    $workflow = app(PedidoWorkflow::class);
    $workflow->registrarComprobante($pedido, $pedido->user, UploadedFile::fake()->image('c.png'));
    $workflow->rechazarPago($pedido->pagos()->latest('id')->first(), adminTienda(), 'El monto no coincide.');

    $this->actingAs($pedido->user)->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Pedidos/Show')
            ->where('pedido.pago.motivo_rechazo', 'El monto no coincide.')
            ->where('instrucciones.puede_subir_comprobante', true));

    $this->actingAs($pedido->user)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), ['archivo' => UploadedFile::fake()->image('nuevo.webp')])
        ->assertSessionHas('success');

    expect($pedido->fresh()->estado_pago)->toBe('en_revision');
});

// ------------------------------------------------------------------ mis pedidos

it('mis pedidos sólo lista y muestra pedidos propios', function () {
    $cliente = clienteTienda();
    $propio = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]]);
    $ajeno = crearPedidoCheckout(clienteTienda(), [[Producto::factory()->create(), 1]]);

    $this->actingAs($cliente)->get(route('cliente.pedidos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Pedidos/Index')
            ->has('pedidos.data', 1)
            ->where('pedidos.data.0.folio', $propio->folio)
            ->where('conteos.todos', 1)
            ->where('conteos.por_pagar', 1));

    $this->actingAs($cliente)->get(route('cliente.pedidos.index', ['estado' => 'entregados']))
        ->assertInertia(fn (Assert $page) => $page->has('pedidos.data', 0)->where('filtro', 'entregados'));

    $this->actingAs($cliente)->get(route('cliente.pedidos.show', $propio))->assertOk();
    $this->actingAs($cliente)->get(route('cliente.pedidos.show', $ajeno))->assertNotFound();
});

it('el historial del cliente oculta las notas internas', function () {
    $cliente = clienteTienda();
    $pedido = crearPedidoCheckout($cliente, [[Producto::factory()->create(), 1]]);
    $workflow = app(PedidoWorkflow::class);
    $workflow->agregarNota($pedido, adminTienda(), 'Cliente difícil, nota interna');
    $workflow->agregarNota($pedido, adminTienda(), 'Tu pedido sale mañana', true);

    $this->actingAs($cliente)->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.historial', fn ($h) => collect($h)->pluck('comentario')->contains('Tu pedido sale mañana')
                && ! collect($h)->pluck('comentario')->contains('Cliente difícil, nota interna')));
});

// ------------------------------------------------------------------ PDF

it('genera el PDF del pedido para el dueño y la hoja de empaque para staff', function () {
    $fake = new PdfServiceFake;
    app()->instance(PdfService::class, $fake);
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)->get(route('cliente.pedidos.pdf', $pedido))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs(clienteTienda())->get(route('cliente.pedidos.pdf', $pedido))->assertNotFound();

    $this->actingAs(vendedorTienda())->get(route('admin.pedidos.hoja-empaque', $pedido))->assertOk();
    $this->actingAs(adminTienda())->get(route('admin.pedidos.pdf', $pedido))->assertOk();
    $this->actingAs(clienteTienda())->get(route('admin.pedidos.hoja-empaque', $pedido))->assertForbidden();

    expect(collect($fake->generados)->pluck('vista')->all())
        ->toBe(['pdf.resumen-pedido', 'pdf.hoja-empaque', 'pdf.resumen-pedido']);
});

it('si Chrome no está disponible responde con un mensaje, no con un 500', function () {
    $fake = new PdfServiceFake;
    $fake->fallar = true;
    app()->instance(PdfService::class, $fake);
    $pedido = pedidoTransferencia();

    $this->actingAs($pedido->user)
        ->from(route('cliente.pedidos.show', $pedido))
        ->get(route('cliente.pedidos.pdf', $pedido))
        ->assertRedirect(route('cliente.pedidos.show', $pedido))
        ->assertSessionHas('error');
});
