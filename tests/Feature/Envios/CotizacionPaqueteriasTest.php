<?php

use App\Events\Pedidos\OpcionesEnvioEnviadas;
use App\Mail\Pedidos\CotizacionEnvioListaMail;
use App\Mail\Pedidos\EnvioCotizadoMail;
use App\Mail\Pedidos\InstruccionesTransferenciaMail;
use App\Models\Direccion;
use App\Models\Paqueteria;
use App\Models\Pedido;
use App\Models\PedidoOpcionEnvio;
use App\Models\Producto;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Cotización de envío con varias paqueterías (contrato §2/§3/§11): el admin/vendedor envía
| opciones, el cliente elige una y sólo entonces ve el total final y cómo pagar.
*/

beforeEach(function () {
    sembrarMetodosPago();
    Mail::fake();

    $config = app(ConfiguracionTienda::class);
    $config->set('banco.nombre', 'Banco Prueba');
    $config->set('banco.beneficiario', 'Mr Lana SA de CV');
    $config->set('banco.clabe', '002010077777777771');

    $this->admin = adminTienda();
    $this->cliente = clienteTienda();
    $this->producto = Producto::factory()->precio(1000)->stock(5)->create();
    $this->estafeta = Paqueteria::factory()->conRastreo('https://rastreo.example.test/estafeta?guia={guia}')->create(['nombre' => 'Estafeta']);
    $this->dhl = Paqueteria::factory()->create(['nombre' => 'DHL']);
    $this->propia = Paqueteria::factory()->create(['nombre' => 'Entrega propia']);
});

function opcionesTres(): array
{
    return [
        ['paqueteria_id' => test()->estafeta->id, 'servicio' => 'Estándar', 'costo' => 180, 'dias_min' => 4, 'dias_max' => 6, 'notas' => 'Entrega a domicilio'],
        ['paqueteria_id' => test()->dhl->id, 'servicio' => 'Express', 'costo' => 420.5, 'dias_min' => 1, 'dias_max' => 2, 'recomendada' => true],
        ['paqueteria_id' => test()->propia->id, 'servicio' => 'Local', 'costo' => 250, 'tiempo_texto' => 'Mismo día en zona metropolitana'],
    ];
}

function pedidoPorCotizar(?Direccion $direccion = null): Pedido
{
    $pedido = crearPedidoCheckout(test()->cliente, [[test()->producto, 1]], direccion: $direccion);
    expect($pedido->envio_estado)->toBe('por_cotizar');

    return $pedido;
}

function pedidoConOpciones(): Pedido
{
    $pedido = pedidoPorCotizar();
    test()->actingAs(test()->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => opcionesTres()])
        ->assertSessionHas('success');

    return $pedido->fresh();
}

it('el admin cotiza 3 opciones y el cliente las ve como tarjetas comparables', function () {
    $pedido = pedidoConOpciones();

    expect($pedido->envio_estado)->toBe('opciones_enviadas')
        ->and($pedido->estatus)->toBe('eleccion_envio')
        ->and($pedido->vence_en)->toBeNull()
        ->and($pedido->opcionesEnvio()->count())->toBe(3);

    $this->actingAs($this->cliente)
        ->get(route('cliente.pedidos.show', $pedido))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cliente/Pedidos/Show')
            ->where('pedido.envio_esperando_eleccion', true)
            ->where('pedido.envio_por_cotizar', true)
            ->has('pedido.opciones_envio', 3)
            // Ordenadas por costo: Estafeta (más económica), Entrega propia, DHL (más rápida y recomendada).
            ->where('pedido.opciones_envio.0.paqueteria', 'Estafeta')
            ->where('pedido.opciones_envio.0.mas_economica', true)
            ->where('pedido.opciones_envio.0.tiempo', '4 a 6 días hábiles')
            ->where('pedido.opciones_envio.1.tiempo', 'Mismo día en zona metropolitana')
            ->where('pedido.opciones_envio.2.paqueteria', 'DHL')
            ->where('pedido.opciones_envio.2.recomendada', true)
            ->where('pedido.opciones_envio.2.mas_rapida', true)
            ->where('pedido.opciones_envio.2.mas_economica', false)
            ->where('urls.elegir_envio', route('cliente.pedidos.envio.elegir', $pedido))
        );

    $this->actingAs($this->cliente)
        ->get(route('checkout.gracias', $pedido))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pedido.opciones_envio', 3));
});

it('el cliente elige una opción: total, saldo, paquetería y vencimiento correctos', function () {
    $pedido = pedidoConOpciones();
    $dhl = $pedido->opcionesEnvio()->where('paqueteria_nombre', 'DHL')->first();

    $this->actingAs($this->cliente)
        ->post(route('cliente.pedidos.envio.elegir', $pedido), ['opcion_id' => $dhl->id])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect((float) $pedido->envio)->toBe(420.5)
        ->and((float) $pedido->total)->toBe(1420.5)
        ->and((float) $pedido->saldo_pendiente)->toBe(1420.5)
        ->and($pedido->paqueteria)->toBe('DHL')
        ->and($pedido->envio_estado)->toBe('cotizado')
        ->and($pedido->estatus)->toBe('pendiente_pago')
        ->and($pedido->vence_en)->not->toBeNull()
        ->and($pedido->vence_en->between(now()->addHours(47), now()->addHours(49)))->toBeTrue()
        ->and((float) $pedido->pagos()->first()->monto)->toBe(1420.5)
        ->and($dhl->fresh()->seleccionada_en)->not->toBeNull()
        ->and($pedido->historial()->where('tipo', 'envio')->latest('id')->first()->comentario)->toContain('DHL');

    // Ahora sí: instrucciones de transferencia (una sola vez) y comprobante habilitado.
    Mail::assertQueued(InstruccionesTransferenciaMail::class, 1);
    Mail::assertNotQueued(EnvioCotizadoMail::class);

    $this->actingAs($this->cliente)
        ->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrucciones.puede_subir_comprobante', true)
            ->where('instrucciones.banco.configurado', true)
            ->where('pedido.envio_por_cotizar', false)
            ->where('pedido.opciones_envio.2.seleccionada', true)
        );
});

it('efectivo en tienda: al elegir recibe el correo de envío cotizado con sus instrucciones', function () {
    $pedido = crearPedidoCheckout($this->cliente, [[$this->producto, 1]], 'efectivo_presencial');
    app(PedidoWorkflow::class)->enviarOpcionesEnvio($pedido, $this->admin, opcionesTres());
    $opcion = $pedido->opcionesEnvio()->first();

    app(PedidoWorkflow::class)->elegirOpcionEnvio($pedido->fresh(), $this->cliente, $opcion->id);

    expect($pedido->fresh()->estatus)->toBe('pendiente_pago')
        ->and($pedido->fresh()->vence_en)->not->toBeNull();
    Mail::assertQueued(EnvioCotizadoMail::class, 1);
    Mail::assertNotQueued(InstruccionesTransferenciaMail::class);
});

it('otro cliente no puede ver ni elegir el envío de un pedido ajeno', function () {
    $pedido = pedidoConOpciones();
    $opcion = $pedido->opcionesEnvio()->first();
    $intruso = clienteTienda();

    $this->actingAs($intruso)
        ->post(route('cliente.pedidos.envio.elegir', $pedido), ['opcion_id' => $opcion->id])
        ->assertNotFound();

    expect(fn () => app(PedidoWorkflow::class)->elegirOpcionEnvio($pedido, $intruso, $opcion->id))
        ->toThrow(AuthorizationException::class);

    expect($pedido->fresh()->envio_estado)->toBe('opciones_enviadas')
        ->and($opcion->fresh()->seleccionada_en)->toBeNull();
});

it('no se puede elegir dos veces ni una opción de otro pedido', function () {
    $pedido = pedidoConOpciones();
    $otro = pedidoConOpciones();
    $ajena = $otro->opcionesEnvio()->first();

    $this->actingAs($this->cliente)
        ->post(route('cliente.pedidos.envio.elegir', $pedido), ['opcion_id' => $ajena->id])
        ->assertSessionHas('error');

    expect($pedido->fresh()->envio_estado)->toBe('opciones_enviadas')
        ->and($ajena->fresh()->seleccionada_en)->toBeNull();

    [$primera, $segunda] = $pedido->opcionesEnvio()->orderBy('id')->take(2)->get()->all();

    $this->actingAs($this->cliente)
        ->post(route('cliente.pedidos.envio.elegir', $pedido), ['opcion_id' => $primera->id])
        ->assertSessionHas('success');

    $this->actingAs($this->cliente)
        ->post(route('cliente.pedidos.envio.elegir', $pedido), ['opcion_id' => $segunda->id])
        ->assertSessionHas('error', 'Ya elegiste tu envío para este pedido.');

    $pedido->refresh();
    expect((float) $pedido->envio)->toBe((float) $primera->costo)
        ->and($segunda->fresh()->seleccionada_en)->toBeNull()
        ->and($pedido->opcionesEnvio()->whereNotNull('seleccionada_en')->count())->toBe(1);
});

it('antes de elegir no hay instrucciones de pago ni se acepta comprobante', function () {
    Storage::fake('local');
    $pedido = pedidoConOpciones();

    $this->actingAs($this->cliente)
        ->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('instrucciones.banco', null)
            ->where('instrucciones.puede_subir_comprobante', false)
        );

    $archivo = UploadedFile::fake()->createWithContent('comprobante.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

    $this->actingAs($this->cliente)
        ->post(route('cliente.pedidos.comprobantes.store', $pedido), ['archivo' => $archivo, 'comprobante' => $archivo])
        ->assertSessionHas('error');

    expect($pedido->fresh()->estado_pago)->toBe('pendiente')
        ->and($pedido->comprobantes()->count())->toBe(0);
    Mail::assertNotQueued(InstruccionesTransferenciaMail::class);
});

it('el vendedor puede cotizar con opciones y el cliente no', function () {
    $pedido = pedidoPorCotizar();

    $this->actingAs($this->cliente)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => opcionesTres()])
        ->assertForbidden();

    expect($pedido->fresh()->envio_estado)->toBe('por_cotizar');

    $this->actingAs(vendedorTienda())
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => opcionesTres()])
        ->assertSessionHas('success');

    expect($pedido->fresh()->envio_estado)->toBe('opciones_enviadas');
});

it('valida las opciones: paquetería activa, servicio, costo y días', function () {
    $pedido = pedidoPorCotizar();
    $inactiva = Paqueteria::factory()->inactiva()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => [
            ['paqueteria_id' => $inactiva->id, 'servicio' => '', 'costo' => -5],
        ]])
        ->assertSessionHasErrors(['opciones.0.paqueteria_id', 'opciones.0.servicio', 'opciones.0.costo']);

    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => [
            ['paqueteria_id' => $this->dhl->id, 'servicio' => 'Express', 'costo' => 100, 'dias_min' => 5, 'dias_max' => 2],
        ]])
        ->assertSessionHas('error');

    expect($pedido->fresh()->envio_estado)->toBe('por_cotizar');
});

it('el correo de cotización se envía una sola vez por juego de opciones', function () {
    $pedido = pedidoConOpciones();

    Mail::assertQueued(CotizacionEnvioListaMail::class, 1);
    Mail::assertQueued(CotizacionEnvioListaMail::class, fn ($m) => $m->hasTo($this->cliente->email));

    // Reintento del mismo evento: no duplica.
    $ids = $pedido->opcionesEnvio()->pluck('id')->all();
    event(new OpcionesEnvioEnviadas($pedido, $ids, false, $this->admin));
    Mail::assertQueued(CotizacionEnvioListaMail::class, 1);

    $html = (new CotizacionEnvioListaMail($pedido))->render();
    expect($html)->toContain('Tu cotización de envío está lista')
        ->toContain('Estafeta')
        ->toContain('Más económica')
        ->toContain('Elegir mi envío');
});

it('re-cotizar antes de que el cliente elija reemplaza las opciones y vuelve a avisar', function () {
    $pedido = pedidoConOpciones();
    $viejas = $pedido->opcionesEnvio()->pluck('id')->all();

    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => [
            ['paqueteria_id' => $this->estafeta->id, 'servicio' => 'Terrestre', 'costo' => 150],
        ]])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->opcionesEnvio()->count())->toBe(1)
        ->and(PedidoOpcionEnvio::query()->whereIn('id', $viejas)->exists())->toBeFalse()
        ->and($pedido->envio_estado)->toBe('opciones_enviadas')
        ->and($pedido->historial()->latest('id')->first()->comentario)->toContain('Actualizamos tu cotización');

    Mail::assertQueued(CotizacionEnvioListaMail::class, 2);
});

it('tras elegir sólo se re-cotiza con motivo y sin pago en revisión', function () {
    Storage::fake('local');
    $pedido = pedidoConOpciones();
    $opcion = $pedido->opcionesEnvio()->where('paqueteria_nombre', 'DHL')->first();
    app(PedidoWorkflow::class)->elegirOpcionEnvio($pedido, $this->cliente, $opcion->id);

    // Sin motivo: rechazado.
    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), ['opciones' => [
            ['paqueteria_id' => $this->estafeta->id, 'servicio' => 'Terrestre', 'costo' => 150],
        ]])
        ->assertSessionHas('error');

    expect($pedido->fresh()->envio_estado)->toBe('cotizado');

    // Con motivo: se quita el costo elegido y el cliente vuelve a elegir.
    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), [
            'motivo' => 'La paquetería ya no da servicio a tu CP',
            'opciones' => [['paqueteria_id' => $this->estafeta->id, 'servicio' => 'Terrestre', 'costo' => 150]],
        ])
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->envio_estado)->toBe('opciones_enviadas')
        ->and((float) $pedido->envio)->toBe(0.0)
        ->and((float) $pedido->total)->toBe(1000.0)
        ->and($pedido->paqueteria)->toBeNull()
        ->and($pedido->vence_en)->toBeNull()
        ->and($pedido->opcionesEnvio()->count())->toBe(1);

    // Elige de nuevo y sube comprobante: ya no se puede re-cotizar.
    app(PedidoWorkflow::class)->elegirOpcionEnvio($pedido, $this->cliente, $pedido->opcionesEnvio()->first()->id);
    app(PedidoWorkflow::class)->registrarComprobante($pedido->fresh(), $this->cliente,
        UploadedFile::fake()->createWithContent('c.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));

    expect(app(PedidoWorkflow::class)->accionesDisponibles($pedido->fresh(), $this->admin)['recotizar_envio'])->toBeFalse();

    $this->actingAs($this->admin)
        ->post(route('admin.pedidos.opciones-envio', $pedido), [
            'motivo' => 'Otro intento de cambio',
            'opciones' => [['paqueteria_id' => $this->dhl->id, 'servicio' => 'Express', 'costo' => 500]],
        ])
        ->assertSessionHas('error');

    expect((float) $pedido->fresh()->envio)->toBe(150.0);
});

it('al registrar el envío la paquetería elegida se ofrece por defecto y el cliente ve el enlace de rastreo', function () {
    $pedido = pedidoConOpciones();
    $estafeta = $pedido->opcionesEnvio()->where('paqueteria_nombre', 'Estafeta')->first();
    app(PedidoWorkflow::class)->elegirOpcionEnvio($pedido, $this->cliente, $estafeta->id);
    Storage::fake('local');
    $wf = app(PedidoWorkflow::class);
    $wf->registrarComprobante($pedido->fresh(), $this->cliente, UploadedFile::fake()->createWithContent('c.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));
    $wf->aprobarPago($pedido->pagos()->latest('id')->firstOrFail(), $this->admin);
    $pedido = $pedido->fresh();

    $this->actingAs($this->admin)
        ->get(route('admin.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.paqueteria', 'Estafeta')
            ->has('paqueterias', 3)
        );

    $wf->iniciarPreparacion($pedido, $this->admin);
    $wf->registrarEnvio($pedido, $this->admin, 'Estafeta', 'AB 123/45');

    $this->actingAs($this->cliente)
        ->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.url_rastreo', 'https://rastreo.example.test/estafeta?guia=AB%20123%2F45')
        );

    // Paquetería sin URL en el catálogo ni conocida: sin enlace.
    $sinUrl = Pedido::factory()->create(['paqueteria' => 'Entrega propia', 'numero_guia' => 'X1']);
    expect($sinUrl->urlRastreo())->toBeNull();
});

it('la copia de la dirección se conserva aunque el cliente edite o borre la dirección', function () {
    $direccion = Direccion::factory()->for($this->cliente)->create(['calle' => 'Av. Original', 'municipio' => 'Puebla', 'estado' => 'Puebla']);
    $pedido = pedidoPorCotizar($direccion);

    expect($pedido->direccion_envio['calle'])->toBe('Av. Original');

    $this->actingAs($this->cliente)
        ->put(route('cliente.direcciones.update', $direccion), [
            ...$direccion->only(['alias', 'nombre_receptor', 'telefono', 'numero_exterior', 'colonia', 'pais', 'codigo_postal']),
            'calle' => 'Calle Nueva',
            'municipio' => 'Cholula',
            'estado' => 'Puebla',
        ])
        ->assertSessionHas('success');

    expect($direccion->fresh()->calle)->toBe('Calle Nueva');

    $this->actingAs($this->cliente)
        ->get(route('cliente.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.direccion.calle', 'Av. Original')
            ->where('pedido.direccion.municipio', 'Puebla'));

    $this->actingAs($this->admin)
        ->get(route('admin.pedidos.show', $pedido))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pedido.direccion.municipio', 'Puebla')
            ->where('pedido.destino', fn ($d) => str_contains((string) $d, 'Puebla')));

    // Con la copia guardada, ya se puede borrar la dirección sin afectar el pedido.
    $this->actingAs($this->cliente)
        ->delete(route('cliente.direcciones.destroy', $direccion))
        ->assertSessionHas('success');

    $pedido->refresh();
    expect($pedido->direccion_id)->toBeNull()
        ->and($pedido->direccionEntrega()['calle'])->toBe('Av. Original');
});

it('el panel de envíos separa por cotizar y esperando al cliente; el tablero lo marca', function () {
    $porCotizar = pedidoPorCotizar();
    $esperando = pedidoConOpciones();

    $this->actingAs($this->admin)
        ->get(route('admin.envios.index', ['tab' => 'esperando_eleccion']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'esperando_eleccion')
            ->has('pedidos.data', 1)
            ->where('pedidos.data.0.id', $esperando->id)
            ->has('pedidos.data.0.opciones_envio', 3)
            ->has('paqueterias', 3));

    $this->actingAs($this->admin)
        ->get(route('admin.envios.index', ['tab' => 'por_cotizar']))
        ->assertInertia(fn (Assert $page) => $page->has('pedidos.data', 1)->where('pedidos.data.0.id', $porCotizar->id));

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operacion.esperando_eleccion', 1)
            ->where('atencion', fn ($items) => collect($items)->contains('clave', 'eleccion-envio')));
});

it('catálogo de paqueterías: alta, URL de rastreo con {guia} y baja segura', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.paqueterias.store'), ['nombre' => 'Paquetexpress', 'url_rastreo' => 'https://example.test/rastreo'])
        ->assertSessionHasErrors('url_rastreo');

    $this->actingAs($this->admin)
        ->post(route('admin.paqueterias.store'), ['nombre' => 'Paquetexpress', 'url_rastreo' => 'https://example.test/rastreo/{guia}', 'activo' => true])
        ->assertSessionHas('success');

    $nueva = Paqueteria::query()->where('nombre', 'Paquetexpress')->firstOrFail();
    expect($nueva->urlRastreo('Z9'))->toBe('https://example.test/rastreo/Z9');

    $this->actingAs(vendedorTienda())->get(route('admin.paqueterias.index'))->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.paqueterias.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Paqueterias/Index')->has('paqueterias', 4));

    // Usada en una cotización → sólo se desactiva.
    pedidoConOpciones();
    $this->actingAs($this->admin)->delete(route('admin.paqueterias.destroy', $this->dhl))->assertSessionHas('success');
    expect($this->dhl->fresh())->not->toBeNull()->and($this->dhl->fresh()->activo)->toBeFalse();

    $this->actingAs($this->admin)->delete(route('admin.paqueterias.destroy', $nueva))->assertSessionHas('success');
    expect($nueva->fresh())->toBeNull();
});
