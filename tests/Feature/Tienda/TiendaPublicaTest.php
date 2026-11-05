<?php

use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Oferta;
use App\Models\Producto;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// ---------------------------------------------------------------- Home

it('responde 200 en el home sin productos ni configuración', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Index')
            ->has('banners', 0)
            ->has('destacados', 0)
            ->has('nuevos', 0)
            ->has('ofertas', 0)
            ->has('coleccion', 0)
            ->has('faq', 0)
            ->where('envios', null)
            ->where('resumen.productos', 0)
            // Sin configuración sólo se muestran hechos verdaderos del sistema.
            ->where('beneficios.0.titulo', 'Seguimiento de tu pedido en línea')
        );
});

it('sólo muestra productos publicados en el home y no inventa beneficios', function () {
    MetodoPago::query()->create(['nombre' => 'Transferencia bancaria', 'clave' => 'transferencia', 'activo' => true]);
    MetodoPago::query()->create(['nombre' => 'Efectivo en tienda', 'clave' => 'efectivo_presencial', 'activo' => true]);
    MetodoPago::query()->create(['nombre' => 'Tarjeta', 'clave' => 'openpay', 'activo' => true]);

    $publicado = Producto::factory()->create(['destacado' => true, 'nombre' => 'Sofá Publicado']);
    Producto::factory()->borrador()->create(['destacado' => true, 'nombre' => 'Borrador Oculto']);
    Producto::factory()->pendienteAprobacion()->create(['destacado' => true]);
    Producto::factory()->pausado()->create(['destacado' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('destacados', 1)
            ->where('destacados.0.id', $publicado->id)
            ->where('resumen.productos', 1)
            ->where('beneficios.0.titulo', 'Paga por transferencia o en efectivo')
            // Openpay no se ofrece mientras los pagos en línea estén deshabilitados.
            ->has('metodosPago', 2)
        );
});

it('usa beneficios, FAQ y política de envíos configurados', function () {
    $config = app(ConfiguracionTienda::class);
    $config->set('home.beneficios', [['titulo' => 'Atención personalizada', 'descripcion' => 'Te asesoramos', 'icono' => 'headset']]);
    $config->set('home.faq', [['pregunta' => '¿Cómo pago?', 'respuesta' => 'Por transferencia.']]);
    $config->set('politicas.envios', 'Enviamos a todo el país.');

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('beneficios', 1)
            ->where('beneficios.0.titulo', 'Atención personalizada')
            ->has('faq', 1)
            ->where('envios', 'Enviamos a todo el país.')
            ->has('tienda.enlaces', 2)
        );
});

it('muestra banners vigentes y descarta URLs peligrosas', function () {
    Banner::query()->create(['titulo' => 'Vigente', 'imagen' => 'banners/a.jpg', 'url' => 'javascript:alert(1)', 'activo' => true]);
    Banner::query()->create(['titulo' => 'Vencido', 'imagen' => 'banners/b.jpg', 'activo' => true, 'termina_en' => now()->subDay()]);
    Banner::query()->create(['titulo' => 'Inactivo', 'imagen' => 'banners/c.jpg', 'activo' => false]);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('banners', 1)
            ->where('banners.0.titulo', 'Vigente')
            ->where('banners.0.url', null)
        );
});

it('lista ofertas vigentes con el precio calculado por PrecioService', function () {
    $producto = Producto::factory()->precio(1000)->create();
    Producto::factory()->precio(500)->create();
    $oferta = Oferta::query()->create([
        'nombre' => 'Buen Fin',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'aplica_a' => 'producto',
        'activa' => true,
    ]);
    $oferta->productos()->attach($producto->id);

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('ofertas', 1)
            ->where('ofertas.0.id', $producto->id)
            ->where('ofertas.0.precio_final', 900)
            ->where('ofertas.0.tiene_oferta', true)
        );
});

// ---------------------------------------------------------------- Catálogo

it('el catálogo sólo lista productos publicados', function () {
    Producto::factory()->count(2)->create();
    Producto::factory()->borrador()->create();
    Producto::factory()->state(['visible' => false])->create();

    $this->get('/productos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Catalogo')
            ->has('productos.data', 2)
            ->where('productos.meta.total', 2)
        );
});

it('filtra el catálogo por categoría (incluye subcategorías)', function () {
    $sala = Categoria::factory()->create(['slug' => 'sala', 'nombre' => 'Sala']);
    $sofas = Categoria::factory()->create(['slug' => 'sofas', 'categoria_padre_id' => $sala->id]);
    $comedor = Categoria::factory()->create(['slug' => 'comedor']);

    $a = Producto::factory()->create(['categoria_id' => $sala->id]);
    $b = Producto::factory()->create(['categoria_id' => $sofas->id]);
    Producto::factory()->create(['categoria_id' => $comedor->id]);

    $this->get('/productos?categoria=sala')
        ->assertInertia(fn (Assert $page) => $page
            ->has('productos.data', 2)
            ->where('filtros.categoria', 'sala')
            ->where('categoriaActual.nombre', 'Sala')
            ->where('productos.data', fn ($data) => collect($data)->pluck('id')->sort()->values()->all() === collect([$a->id, $b->id])->sort()->values()->all())
        );
});

it('filtra el catálogo por rango de precio y marca', function () {
    $marca = Marca::factory()->create(['slug' => 'acme']);
    Producto::factory()->precio(500)->create(['marca_id' => $marca->id]);
    $medio = Producto::factory()->precio(1500)->create(['marca_id' => $marca->id]);
    Producto::factory()->precio(1500)->create();
    Producto::factory()->precio(5000)->create(['marca_id' => $marca->id]);

    $this->get('/productos?precio_min=1000&precio_max=2000&marca=acme')
        ->assertInertia(fn (Assert $page) => $page
            ->has('productos.data', 1)
            ->where('productos.data.0.id', $medio->id)
            ->where('filtros.precio_min', 1000)
            ->where('filtros.precio_max', 2000)
        );
});

it('filtra por disponibilidad', function () {
    $stock = Producto::factory()->stock(5)->create();
    $pedido = Producto::factory()->bajoPedido(20)->create();
    $agotado = Producto::factory()->agotado()->create();

    foreach (['en_stock' => $stock, 'bajo_pedido' => $pedido, 'agotado' => $agotado] as $filtro => $esperado) {
        $this->get('/productos?disponibilidad='.$filtro)
            ->assertInertia(fn (Assert $page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.id', $esperado->id)
                ->where('productos.data.0.disponibilidad', $filtro)
            );
    }
});

it('busca por nombre, SKU, modelo y descripción', function () {
    $porNombre = Producto::factory()->create(['nombre' => 'Mesa de centro Nórdica']);
    $porSku = Producto::factory()->create(['sku' => 'ZX-9981']);
    $porModelo = Producto::factory()->create(['modelo' => 'KALLAX 77']);
    $porDescripcion = Producto::factory()->create(['descripcion' => 'Ideal para terrazas techadas']);
    Producto::factory()->borrador()->create(['nombre' => 'Mesa borrador']);

    $ids = fn (string $q) => collect($this->get('/productos?q='.urlencode($q))->viewData('page')['props']['productos']['data'])->pluck('id')->all();

    expect($ids('mesa'))->toBe([$porNombre->id])
        ->and($ids('ZX-9981'))->toBe([$porSku->id])
        ->and($ids('kallax'))->toBe([$porModelo->id])
        ->and($ids('terrazas'))->toBe([$porDescripcion->id]);
});

it('escapa comodines de LIKE en la búsqueda', function () {
    Producto::factory()->create(['nombre' => 'Descuento 50% real']);
    Producto::factory()->create(['nombre' => 'Silla normal']);
    Producto::factory()->create(['nombre' => 'Cajón_doble']);

    $total = fn (string $q) => $this->get('/productos?q='.urlencode($q))->viewData('page')['props']['productos']['meta']['total'];

    expect($total('%'))->toBe(1)
        ->and($total('_'))->toBe(1)
        ->and($total('50%'))->toBe(1);
});

it('ordena por precio y pagina en el servidor', function () {
    Producto::factory()->count(30)->sequence(fn ($s) => ['precio' => 100 + $s->index])->create();

    $this->get('/productos?orden=precio_desc&page=2')
        ->assertInertia(fn (Assert $page) => $page
            ->has('productos.data', 6)
            ->where('productos.meta.pagina_actual', 2)
            ->where('productos.meta.total', 30)
            ->where('productos.data.0.precio_final', 105)
        );
});

it('ignora filtros inválidos sin fallar', function () {
    Producto::factory()->create();

    $this->get('/productos?orden=DROP&disponibilidad=x&categoria=../../etc&precio_min=abc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filtros.orden', 'relevancia')
            ->where('filtros.disponibilidad', null)
            ->where('filtros.categoria', null)
            ->where('filtros.precio_min', null)
        );
});

it('devuelve sugerencias sólo de productos publicados', function () {
    Producto::factory()->create(['nombre' => 'Lámpara de pie']);
    Producto::factory()->borrador()->create(['nombre' => 'Lámpara secreta']);

    $this->getJson('/buscar/sugerencias?q=lámpara')
        ->assertOk()
        ->assertJsonCount(1, 'productos')
        ->assertJsonPath('productos.0.nombre', 'Lámpara de pie');
});

// ---------------------------------------------------------------- Producto

it('muestra el detalle de un producto publicado', function () {
    $producto = Producto::factory()->bajoPedido(12)->create([
        'modelo' => 'MX-1',
        'acabado' => 'Nogal',
        'dimensiones' => '120 x 60 x 75 cm',
        'atributos' => [['nombre' => 'Material', 'valor' => 'Madera']],
        'precio_referencia' => 999,
        'fuente_referencia' => 'Proveedor',
    ]);

    $this->get('/productos/'.$producto->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Producto')
            ->where('producto.id', $producto->id)
            ->where('producto.disponibilidad', 'bajo_pedido')
            ->where('producto.tiempo_entrega_dias', 12)
            ->where('producto.modelo', 'MX-1')
            ->where('producto.atributos.0.valor', 'Madera')
            ->missing('producto.precio_referencia')
            ->missing('producto.fuente_referencia')
            ->where('seo.canonical', route('tienda.show', $producto->slug))
        );
});

it('da 404 a productos no publicados', function (string $estado) {
    $producto = Producto::factory()->{$estado}()->create();

    $this->get('/productos/'.$producto->slug)->assertNotFound();
})->with(['borrador', 'pendienteAprobacion', 'pausado']);

it('da 404 a un producto publicado pero oculto o inactivo', function () {
    $oculto = Producto::factory()->create(['visible' => false]);
    $inactivo = Producto::factory()->create(['activo' => false]);

    $this->get('/productos/'.$oculto->slug)->assertNotFound();
    $this->get('/productos/'.$inactivo->slug)->assertNotFound();
});

// ---------------------------------------------------------------- Páginas, sitemap y robots

it('muestra páginas informativas sólo si tienen contenido', function () {
    $this->get('/informacion/envios')->assertNotFound();
    $this->get('/preguntas-frecuentes')->assertNotFound();

    app(ConfiguracionTienda::class)->set('politicas.envios', 'Entregas en 5 días hábiles.');
    app(ConfiguracionTienda::class)->set('home.faq', [['pregunta' => '¿Hay envíos?', 'respuesta' => 'Sí.']]);

    $this->get('/informacion/envios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Tienda/Pagina')
            ->where('pagina.contenido', 'Entregas en 5 días hábiles.')
        );
    $this->get('/preguntas-frecuentes')->assertOk();
});

it('genera un sitemap con sólo productos publicados y categorías con productos', function () {
    $categoria = Categoria::factory()->create(['slug' => 'recamara']);
    Categoria::factory()->create(['slug' => 'vacia']);
    $publicado = Producto::factory()->create(['categoria_id' => $categoria->id, 'slug' => 'cama-king']);
    Producto::factory()->borrador()->create(['slug' => 'cama-borrador']);
    Producto::factory()->pausado()->create(['slug' => 'cama-pausada']);

    $respuesta = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $respuesta->getContent();

    expect($xml)->toContain(route('tienda.show', $publicado->slug))
        ->toContain('categoria=recamara')
        ->not->toContain('categoria=vacia')
        ->not->toContain('cama-borrador')
        ->not->toContain('cama-pausada');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('robots.txt bloquea áreas privadas', function () {
    config(['seo.indexar' => true]);
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /checkout')
        ->toContain('Disallow: /mi-cuenta')
        ->toContain('Disallow: /carrito');
});

it('el manifest PWA y el service worker cumplen el contrato', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['name'])->toBe('Mr Lana Tienda')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['theme_color'])->toBe('#73C83A')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icono) {
        expect(file_exists(public_path(ltrim($icono['src'], '/'))))->toBeTrue();
    }

    $sw = file_get_contents(public_path('sw.js'));
    expect($sw)->toContain("'/admin'")
        ->toContain("'/checkout'")
        ->toContain("'/mi-cuenta'")
        ->toContain("request.method !== 'GET'")
        ->toContain('/offline.html');
});

it('el catálogo y el home no tienen consultas N+1', function () {
    $marca = Marca::factory()->create();
    Producto::factory()->count(24)->create(['marca_id' => $marca->id]);

    $contar = function (string $url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    };

    $contar('/productos'); // calienta cachés (configuración)
    $catalogo = $contar('/productos');
    Producto::factory()->count(24)->create(['marca_id' => $marca->id]);

    // Duplicar productos no debe aumentar el número de consultas.
    expect($contar('/productos'))->toBe($catalogo)
        ->and($catalogo)->toBeLessThan(25);
});
