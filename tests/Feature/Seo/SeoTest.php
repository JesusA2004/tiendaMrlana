<?php

use App\Http\Controllers\Publico\TiendaController;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\Catalogo\ImagenesProducto;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Seo\ImagenOgProducto;
use App\Support\Seo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const DOMINIO = 'https://tienda.mr-lana.com';

beforeEach(function () {
    config(['app.url' => DOMINIO, 'seo.indexar' => true]);
});

/** Contenido de una metaetiqueta del HTML crudo (lo que ve el crawler de WhatsApp). */
function meta(string $html, string $atributo, string $nombre): ?string
{
    $patron = '/<meta\s+'.$atributo.'="'.preg_quote($nombre, '/').'"[^>]*\scontent="([^"]*)"/u';

    return preg_match($patron, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
}

/** @return list<array<string, mixed>> */
function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return array_map(function (string $json) {
        $datos = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        expect($datos['@context'])->toBe('https://schema.org');

        return $datos;
    }, $m[1]);
}

function nodo(array $nodos, string $tipo): ?array
{
    return collect($nodos)->first(fn (array $n) => in_array($tipo, (array) ($n['@type'] ?? []), true));
}

function productoConImagen(array $atributos = []): Producto
{
    $producto = Producto::factory()->precio(2499.5)->stock(3)->create($atributos);
    $ruta = UploadedFile::fake()->image('sofa.jpg', 900, 700)->store('productos/'.$producto->id, 'public');
    $producto->imagenes()->create(['ruta' => $ruta, 'orden' => 1, 'principal' => true]);
    $producto->forceFill(['imagen_principal' => $ruta])->save();

    return $producto;
}

// ---------------------------------------------------------------- HTML del servidor

it('el home trae Open Graph absoluto y JSON-LD Organization + WebSite en el HTML crudo', function () {
    app(ConfiguracionTienda::class)->set('redes.facebook', 'https://facebook.com/mrlana');

    $html = $this->get('/')->assertOk()->getContent();

    expect(meta($html, 'property', 'og:title'))->not->toBeNull()
        ->and(meta($html, 'property', 'og:site_name'))->toBe('Mr Lana')
        ->and(meta($html, 'property', 'og:locale'))->toBe('es_MX')
        ->and(meta($html, 'property', 'og:type'))->toBe('website')
        ->and(meta($html, 'property', 'og:url'))->toBe(DOMINIO.'/')
        ->and(meta($html, 'property', 'og:image'))->toBe(DOMINIO.'/icons/og-image.jpg')
        ->and(meta($html, 'property', 'og:image:secure_url'))->toBe(DOMINIO.'/icons/og-image.jpg')
        ->and(meta($html, 'property', 'og:image:width'))->toBe('1200')
        ->and(meta($html, 'property', 'og:image:height'))->toBe('630')
        ->and(meta($html, 'property', 'og:image:type'))->toBe('image/jpeg')
        ->and(meta($html, 'name', 'twitter:card'))->toBe('summary_large_image')
        ->and(meta($html, 'name', 'robots'))->toStartWith('index, follow')
        ->and($html)->toContain('<link rel="canonical" data-inertia="canonical" href="'.DOMINIO.'/">')
        // Sin duplicados: una sola etiqueta por propiedad.
        ->and(substr_count($html, 'property="og:title"'))->toBe(1)
        ->and(substr_count($html, '<title'))->toBe(1);

    $nodos = jsonLd($html);
    expect(nodo($nodos, 'Organization'))->toMatchArray(['name' => 'Mr Lana', 'url' => DOMINIO.'/', 'sameAs' => ['https://facebook.com/mrlana']])
        ->and(nodo($nodos, 'WebSite')['potentialAction']['target']['urlTemplate'])->toBe(DOMINIO.'/productos?q={search_term_string}')
        // Marca: nombre del sitio + variantes de búsqueda.
        ->and(nodo($nodos, 'OnlineStore')['alternateName'])->toContain('Tienda Mr Lana')
        ->and(nodo($nodos, 'WebSite')['name'])->toBe('Mr Lana')
        ->and(nodo($nodos, 'WebSite')['alternateName'])->toContain('Tienda Mr Lana', 'MrLana')
        ->and($html)->toContain('<title data-inertia="">Mr Lana | Tienda en línea de muebles y artículos para el hogar</title>')
        ->and($html)->toContain('hreflang="es-MX"')
        ->and(meta($html, 'name', 'description'))->toContain('Tienda Mr Lana');
});

it('la imagen OG general existe, es JPG 1200x630 y pesa menos de 300 KB', function () {
    $ruta = public_path('icons/og-image.jpg');
    $info = getimagesize($ruta);

    expect($info[0])->toBe(1200)
        ->and($info[1])->toBe(630)
        ->and($info['mime'])->toBe('image/jpeg')
        ->and(filesize($ruta))->toBeLessThan(300 * 1024);
});

it('el catálogo y las categorías tienen título, descripción y canonical propios', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Recámara', 'slug' => 'recamara', 'descripcion' => 'Camas y burós para descansar.']);
    Producto::factory()->count(2)->create(['categoria_id' => $categoria->id]);

    $html = $this->get('/productos?orden=precio_asc&marca=x')->assertOk()->getContent();
    expect(meta($html, 'property', 'og:url'))->toBe(DOMINIO.'/productos')
        ->and(meta($html, 'property', 'og:image'))->toStartWith('https://')
        ->and(meta($html, 'name', 'twitter:card'))->toBe('summary_large_image')
        ->and(meta($html, 'property', 'og:title'))->toBe('Catálogo');

    $html = $this->get('/productos?categoria=recamara&page=2')->assertOk()->getContent();
    expect(meta($html, 'property', 'og:url'))->toBe(DOMINIO.'/productos?categoria=recamara&page=2')
        ->and(meta($html, 'property', 'og:title'))->toBe('Recámara · Página 2')
        ->and(meta($html, 'name', 'description'))->toBe('Camas y burós para descansar.')
        ->and(nodo(jsonLd($html), 'BreadcrumbList')['itemListElement'][2]['item'])->toBe(DOMINIO.'/productos?categoria=recamara');
});

it('el producto trae OG de producto con imagen derivada 1200x630, precio y JSON-LD Product + BreadcrumbList', function () {
    Storage::fake('public');
    $categoria = Categoria::factory()->create(['nombre' => 'Sala', 'slug' => 'sala']);
    $marca = Marca::factory()->create(['nombre' => 'Lana Home']);
    $producto = productoConImagen(['nombre' => 'Sofá "Nube" <3 plazas>', 'sku' => 'SOF-001', 'categoria_id' => $categoria->id, 'marca_id' => $marca->id]);

    $html = $this->get('/productos/'.$producto->slug)->assertOk()->getContent();

    $imagen = meta($html, 'property', 'og:image');
    expect($imagen)->toStartWith(DOMINIO.'/storage/og/productos/'.$producto->id.'-')
        ->and($imagen)->toEndWith('.jpg')
        ->and(meta($html, 'property', 'og:type'))->toBe('product')
        ->and(meta($html, 'property', 'og:title'))->toBe('Sofá "Nube" <3 plazas>')
        ->and(meta($html, 'property', 'og:url'))->toBe(DOMINIO.'/productos/'.$producto->slug)
        ->and(meta($html, 'property', 'og:image:width'))->toBe('1200')
        ->and(meta($html, 'property', 'og:image:alt'))->toBe('Sofá "Nube" <3 plazas>')
        ->and(meta($html, 'property', 'product:price:amount'))->toBe('2499.50')
        ->and(meta($html, 'property', 'product:price:currency'))->toBe('MXN')
        ->and(meta($html, 'property', 'product:availability'))->toBe('in stock')
        ->and(meta($html, 'name', 'twitter:card'))->toBe('summary_large_image')
        // Todo escapado: nada de HTML del nombre sin escapar.
        ->and($html)->not->toContain('content="Sofá "Nube"')
        ->and($html)->toContain('<meta property="og:title" data-inertia="og:title" content="Sofá &quot;Nube&quot; &lt;3 plazas&gt;">');

    // La imagen derivada existe en el disco público con el tamaño correcto.
    $archivo = str_replace(DOMINIO.'/storage/', '', $imagen);
    Storage::disk('public')->assertExists($archivo);
    $info = getimagesizefromstring(Storage::disk('public')->get($archivo));
    expect([$info[0], $info[1], $info['mime']])->toBe([1200, 630, 'image/jpeg'])
        ->and(strlen(Storage::disk('public')->get($archivo)))->toBeLessThan(300 * 1024);

    $nodos = jsonLd($html);
    $p = nodo($nodos, 'Product');
    expect($p['name'])->toBe('Sofá "Nube" <3 plazas>')
        ->and($p['sku'])->toBe('SOF-001')
        ->and($p['brand']['name'])->toBe('Lana Home')
        ->and($p['image'][0])->toStartWith(DOMINIO.'/storage/productos/')
        ->and($p['offers'])->toMatchArray([
            'price' => '2499.50',
            'priceCurrency' => 'MXN',
            'availability' => 'https://schema.org/InStock',
            'url' => DOMINIO.'/productos/'.$producto->slug,
        ]);

    $migas = nodo($nodos, 'BreadcrumbList')['itemListElement'];
    expect(collect($migas)->pluck('name')->all())->toBe(['Inicio', 'Catálogo', 'Sala', 'Sofá "Nube" <3 plazas>']);
});

it('bajo pedido se publica como PreOrder y sin imagen usa la OG general', function () {
    $producto = Producto::factory()->bajoPedido(10)->precio(1000)->create(['imagen_principal' => null]);

    $html = $this->get('/productos/'.$producto->slug)->assertOk()->getContent();

    expect(meta($html, 'property', 'og:image'))->toBe(DOMINIO.'/icons/og-image.jpg')
        ->and(meta($html, 'property', 'product:availability'))->toBe('preorder')
        ->and(nodo(jsonLd($html), 'Product')['offers']['availability'])->toBe('https://schema.org/PreOrder');
});

it('un producto sin precio publicado no emite offers ni metas de precio', function () {
    $producto = Producto::factory()->precio(500)->create();
    $producto->load(['categoria', 'marca', 'imagenes']);

    $metodo = new ReflectionMethod(TiendaController::class, 'seoProducto');
    $seo = $metodo->invoke(app(TiendaController::class), $producto, ['precio_final' => null], 'en_stock', collect(), 'Descripción')->toArray();

    expect($seo['producto'])->toBeNull()
        ->and(nodo($seo['json_ld'], 'Product'))->not->toHaveKey('offers')
        ->and(Seo::nuevo()->producto(0.0, 'en_stock')->toArray()['producto'])->toBeNull();
});

it('al cambiar la imagen principal se regenera la OG y se borra la anterior', function () {
    Storage::fake('public');
    $producto = productoConImagen();
    $servicio = app(ImagenOgProducto::class);

    $primera = $servicio->para($producto->fresh());
    expect($primera)->not->toBeNull();
    $archivoPrimero = str_replace('/storage/', '', parse_url($primera['url'], PHP_URL_PATH));

    $nueva = UploadedFile::fake()->image('otra.png', 400, 900)->store('productos/'.$producto->id, 'public');
    $producto->imagenes()->update(['principal' => false]);
    $producto->imagenes()->create(['ruta' => $nueva, 'orden' => 0, 'principal' => true]);
    app(ImagenesProducto::class)->sincronizarPrincipal($producto->fresh());

    $segunda = $servicio->para($producto->fresh(), null, false);
    expect($segunda['url'])->not->toBe($primera['url']);
    Storage::disk('public')->assertMissing($archivoPrimero);
});

// ---------------------------------------------------------------- noindex

it('búsquedas, carrito y páginas privadas llevan noindex', function () {
    Producto::factory()->create(['nombre' => 'Mesa']);

    expect(meta($this->get('/productos?q=mesa')->getContent(), 'name', 'robots'))->toBe('noindex, follow')
        ->and(meta($this->get('/carrito')->getContent(), 'name', 'robots'))->toBe('noindex, follow')
        ->and(meta($this->get('/login')->getContent(), 'name', 'robots'))->toBe('noindex, follow');
});

it('fuera de producción ninguna página se indexa', function () {
    config(['seo.indexar' => false]);

    expect(meta($this->get('/')->getContent(), 'name', 'robots'))->toBe('noindex, nofollow');
});

// ---------------------------------------------------------------- robots y sitemap

it('robots.txt en producción anuncia el sitemap absoluto', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.DOMINIO.'/sitemap.xml', false)
        ->assertSee('Disallow: /admin', false)
        ->assertDontSee("Disallow: /\n", false);

    expect(file_exists(public_path('robots.txt')))->toBeFalse();
});

it('robots.txt fuera de producción bloquea todo el sitio', function () {
    config(['seo.indexar' => false]);

    $texto = $this->get('/robots.txt')->assertOk()->getContent();

    expect($texto)->toContain("User-agent: *\nDisallow: /\n")->not->toContain('Sitemap:');
});

it('el sitemap usa URL absolutas de APP_URL, lastmod e imágenes', function () {
    Storage::fake('public');
    $producto = productoConImagen(['slug' => 'sofa-nube']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    $doc = simplexml_load_string($xml);
    expect($doc)->not->toBeFalse();

    $locs = collect($doc->url)->map(fn ($u) => (string) $u->loc);
    expect($locs->every(fn ($l) => str_starts_with($l, DOMINIO.'/')))->toBeTrue()
        ->and($locs)->toContain(DOMINIO.'/productos/sofa-nube')
        ->and($xml)->toContain('<lastmod>')
        ->and($xml)->toContain('<image:loc>'.DOMINIO.'/storage/productos/'.$producto->id.'/');
});

// ---------------------------------------------------------------- pie de página

it('el pie de página recibe enlaces y dirección en todas las páginas públicas', function () {
    $config = app(ConfiguracionTienda::class);
    $config->set('politicas.privacidad', 'Tus datos están protegidos.');
    $config->set('home.faq', [['pregunta' => '¿Envían?', 'respuesta' => 'Sí.']]);
    $config->set('contacto.direccion', 'Av. Siempre Viva 742');
    $producto = Producto::factory()->create();

    foreach (['/', '/productos', '/productos/'.$producto->slug, '/carrito', '/informacion/privacidad', '/preguntas-frecuentes'] as $url) {
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tienda.enlaces', 2)
            ->where('tienda.enlaces.0.titulo', 'Aviso de privacidad')
            ->where('tienda.enlaces.1.titulo', 'Preguntas frecuentes')
            ->where('tienda.contacto.direccion', 'Av. Siempre Viva 742')
            ->missing('enlacesInformativos')
        );
    }
});
