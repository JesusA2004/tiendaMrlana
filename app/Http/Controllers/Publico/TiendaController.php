<?php

namespace App\Http\Controllers\Publico;

use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadVenta;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Oferta;
use App\Models\Producto;
use App\Services\Catalogo\PrecioService;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Seo\ImagenOgProducto;
use App\Support\ImagenUrl;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tienda pública: home, catálogo, detalle de producto, páginas informativas, sitemap y robots.
 *
 * Reglas: sólo productos `publicados()`; precios siempre vía PrecioService;
 * ningún dato comercial inventado (todo sale de BD/configuración y si falta se omite).
 */
class TiendaController extends Controller
{
    public const POR_PAGINA = 24;

    /** Columnas necesarias para tarjetas de producto. */
    private const COLUMNAS_TARJETA = [
        'productos.id',
        'productos.categoria_id',
        'productos.marca_id',
        'productos.sku',
        'productos.nombre',
        'productos.slug',
        'productos.descripcion',
        'productos.precio',
        'productos.stock',
        'productos.imagen_principal',
        'productos.destacado',
        'productos.modalidad_venta',
        'productos.tiempo_entrega_dias',
        'productos.modelo',
        'productos.publicado_en',
        'productos.created_at',
    ];

    /** Categorías de la colección de muebles (por slug) en el orden en que se muestran. */
    private const COLECCION_MUEBLES = [
        'sala' => 'Sala',
        'comedor' => 'Comedor',
        'recamara' => 'Recámara',
        'home-office' => 'Home Office',
        'organizacion' => 'Organización',
        'terraza' => 'Terraza',
    ];

    /** Páginas informativas administrables: slug público → clave de configuración. */
    public const PAGINAS = [
        'envios' => ['clave' => 'politicas.envios', 'titulo' => 'Envíos y entregas'],
        'devoluciones' => ['clave' => 'politicas.devoluciones', 'titulo' => 'Cambios y devoluciones'],
        'privacidad' => ['clave' => 'politicas.privacidad', 'titulo' => 'Aviso de privacidad'],
        'terminos' => ['clave' => 'politicas.terminos', 'titulo' => 'Términos y condiciones'],
    ];

    public const ORDENES = ['relevancia', 'precio_asc', 'precio_desc', 'nuevos', 'nombre'];

    public const DISPONIBILIDADES = ['en_stock', 'bajo_pedido', 'agotado'];

    public function __construct(
        private readonly PrecioService $precios,
        private readonly ConfiguracionTienda $config,
        private readonly ImagenOgProducto $imagenOg,
    ) {}

    // ------------------------------------------------------------------ Home

    public function index(): Response
    {
        $categorias = $this->categoriasConProductos();

        $destacados = $this->tarjetas(
            $this->consultaPublica()->where('productos.destacado', true)->latest('productos.publicado_en')->latest('productos.id')->take(8)->get(self::COLUMNAS_TARJETA)
        );

        $nuevos = $this->tarjetas(
            $this->consultaPublica()->orderByDesc('productos.publicado_en')->orderByDesc('productos.id')->take(8)->get(self::COLUMNAS_TARJETA)
        );

        $coleccion = $categorias
            ->filter(fn (array $c) => array_key_exists($c['slug'], self::COLECCION_MUEBLES))
            ->sortBy(fn (array $c) => array_search($c['slug'], array_keys(self::COLECCION_MUEBLES), true))
            ->values();

        $resumen = [
            'productos' => Producto::query()->publicados()->count(),
            'categorias' => $categorias->count(),
            'marcas' => Producto::query()->publicados()->whereNotNull('marca_id')->distinct()->count('marca_id'),
        ];

        return Inertia::render('Tienda/Index', [
            'banners' => $this->banners(),
            'categorias' => $categorias->take(12)->values(),
            'destacados' => $destacados,
            'nuevos' => $nuevos,
            'ofertas' => $this->productosEnOferta(8),
            'coleccion' => $coleccion,
            'resumen' => $resumen,
            'beneficios' => $this->beneficios(),
            'envios' => $this->config->string('politicas.envios'),
            'metodosPago' => $this->metodosPagoPublicos(),
            'faq' => $this->faq(),
            'seo' => Seo::nuevo()
                ->titulo('Muebles y artículos para el hogar')
                ->tituloSocial(Seo::sitio().' · '.($this->config->string('tienda.eslogan') ?? 'Muebles y artículos para el hogar'))
                ->canonical(route('home', [], false))
                ->conOrganizacion()
                ->toArray(),
        ]);
    }

    // ------------------------------------------------------------------ Catálogo

    public function catalogo(Request $request): Response
    {
        $filtros = $this->filtrosDesdeRequest($request);

        $consulta = $this->consultaPublica();
        $this->aplicarFiltros($consulta, $filtros);
        $this->aplicarOrden($consulta, $filtros);

        $paginador = $consulta
            ->paginate(self::POR_PAGINA, self::COLUMNAS_TARJETA)
            ->withQueryString();

        $productos = $this->tarjetas(new EloquentCollection($paginador->items()));

        $rango = Producto::query()->publicados()
            ->selectRaw('MIN(precio) as minimo, MAX(precio) as maximo')
            ->first();

        $categoriaActual = $filtros['categoria']
            ? Categoria::query()->where('slug', $filtros['categoria'])->where('activa', true)->first(['id', 'nombre', 'slug', 'descripcion'])
            : null;

        return Inertia::render('Tienda/Catalogo', [
            'productos' => [
                'data' => $productos,
                'meta' => [
                    'pagina_actual' => $paginador->currentPage(),
                    'ultima_pagina' => $paginador->lastPage(),
                    'por_pagina' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'desde' => $paginador->firstItem(),
                    'hasta' => $paginador->lastItem(),
                ],
                'links' => collect($paginador->linkCollection())
                    ->map(fn (array $l) => ['url' => $l['url'], 'label' => strip_tags((string) $l['label']), 'active' => (bool) $l['active']])
                    ->values(),
            ],
            'filtros' => $filtros,
            'categorias' => $this->categoriasConProductos(),
            'marcas' => $this->marcasConProductos(),
            'rangoPrecio' => [
                'min' => $rango && $rango->minimo !== null ? (float) floor((float) $rango->minimo) : 0,
                'max' => $rango && $rango->maximo !== null ? (float) ceil((float) $rango->maximo) : 0,
            ],
            'categoriaActual' => $categoriaActual ? [
                'nombre' => $categoriaActual->nombre,
                'slug' => $categoriaActual->slug,
                'descripcion' => $categoriaActual->descripcion,
            ] : null,
            'seo' => $this->seoCatalogo($filtros, $categoriaActual, $paginador->currentPage(), $paginador->total())->toArray(),
        ]);
    }

    /**
     * Sugerencias del buscador (JSON ligero, máx. 6). Sólo publicados.
     */
    public function sugerencias(Request $request)
    {
        $q = $this->limpiarBusqueda($request->input('q'));

        if ($q === null || mb_strlen($q) < 2) {
            return response()->json(['productos' => [], 'categorias' => []]);
        }

        $productos = $this->consultaPublica();
        $this->aplicarBusqueda($productos, $q);
        $this->ordenarPorRelevancia($productos, $q);

        $lista = $productos->take(6)->get(self::COLUMNAS_TARJETA);
        $precios = $this->precios->calcularMuchos($lista);

        $categorias = Categoria::query()
            ->where('activa', true)
            ->whereRaw("nombre LIKE ? ESCAPE '!'", ['%'.$this->escaparLike($q).'%'])
            ->whereHas('productos', fn (Builder $p) => $p->publicados())
            ->orderBy('nombre')
            ->take(3)
            ->get(['id', 'nombre', 'slug']);

        return response()->json([
            'productos' => $lista->map(fn (Producto $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'slug' => $p->slug,
                'imagen' => $p->imagenPrincipalUrl(),
                'precio_final' => $precios->get($p->id)['precio_final'] ?? null,
                'categoria' => $p->categoria?->nombre,
            ])->values(),
            'categorias' => $categorias->map(fn (Categoria $c) => ['nombre' => $c->nombre, 'slug' => $c->slug])->values(),
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Categorías con productos publicados para el menú de la barra superior (JSON).
     */
    public function menu()
    {
        return response()->json([
            'categorias' => $this->categoriasConProductos()
                ->map(fn (array $c) => ['nombre' => $c['nombre'], 'slug' => $c['slug'], 'imagen' => $c['imagen'], 'total' => $c['total']])
                ->values(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    // ------------------------------------------------------------------ Detalle

    public function show(Producto $producto): Response
    {
        abort_unless($producto->estaPublicado(), 404);

        $producto->load([
            'categoria:id,nombre,slug,categoria_padre_id',
            'categoria.padre:id,nombre,slug',
            'marca:id,nombre,slug,logo',
            'imagenes:id,producto_id,ruta,orden,principal',
            'ofertas',
        ]);

        $precio = $this->precios->calcular($producto);

        $relacionadosBase = $this->consultaPublica()
            ->where('productos.id', '!=', $producto->id)
            ->when($producto->categoria_id, fn (Builder $q) => $q->where('productos.categoria_id', $producto->categoria_id))
            ->orderByDesc('productos.destacado')
            ->orderByDesc('productos.publicado_en')
            ->take(8)
            ->get(self::COLUMNAS_TARJETA);

        $imagenes = $producto->imagenes
            ->sortBy([['principal', 'desc'], ['orden', 'asc'], ['id', 'asc']])
            ->map(fn ($img) => ImagenUrl::resolver($img->ruta))
            ->filter()
            ->values();

        if ($imagenes->isEmpty() && $producto->imagen_principal) {
            $imagenes = collect([ImagenUrl::resolver($producto->imagen_principal)])->filter()->values();
        }

        $disponibilidad = $this->disponibilidad($producto);
        $maximo = $disponibilidad === 'bajo_pedido' ? 99 : max(0, min(99, (int) $producto->stock));

        $atributos = collect(is_array($producto->atributos) ? $producto->atributos : [])
            ->filter(fn ($a) => is_array($a) && filled($a['nombre'] ?? null) && filled($a['valor'] ?? null))
            ->map(fn (array $a) => ['nombre' => (string) $a['nombre'], 'valor' => (string) $a['valor']])
            ->values();

        $medidas = collect([
            'Alto' => $producto->alto,
            'Ancho' => $producto->ancho,
            'Largo / fondo' => $producto->largo,
        ])->filter(fn ($v) => $v !== null && (float) $v > 0)
            ->map(fn ($v, $k) => ['nombre' => $k, 'valor' => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.').' cm'])
            ->values();

        if ($producto->peso !== null && (float) $producto->peso > 0) {
            $medidas->push(['nombre' => 'Peso', 'valor' => rtrim(rtrim(number_format((float) $producto->peso, 2, '.', ''), '0'), '.').' kg']);
        }

        $descripcionMeta = $producto->meta_descripcion
            ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($producto->descripcion ?: $producto->descripcion_larga)))), 155, '…');

        return Inertia::render('Tienda/Producto', [
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'slug' => $producto->slug,
                'sku' => $producto->sku,
                'descripcion' => $producto->descripcion,
                'descripcion_larga' => $producto->descripcion_larga,
                'modelo' => $producto->modelo,
                'acabado' => $producto->acabado,
                'dimensiones' => $producto->dimensiones,
                'atributos' => $atributos,
                'medidas' => $medidas,
                'imagenes' => $imagenes,
                'precio_original' => $precio['precio_original'],
                'precio_final' => $precio['precio_final'],
                'descuento' => $precio['descuento'],
                'tiene_oferta' => $precio['tiene_oferta'],
                'oferta' => $precio['oferta'] ? [
                    'nombre' => $precio['oferta']['nombre'],
                    'tipo' => $precio['oferta']['tipo'],
                    'valor' => $precio['oferta']['valor'],
                    'termina_en' => $precio['oferta']['termina_en'],
                ] : null,
                'disponibilidad' => $disponibilidad,
                'stock' => $disponibilidad === 'en_stock' ? (int) $producto->stock : null,
                'tiempo_entrega_dias' => $producto->esBajoPedido() ? $producto->tiempo_entrega_dias : null,
                'cantidad_maxima' => $maximo,
                'categoria' => $producto->categoria ? [
                    'nombre' => $producto->categoria->nombre,
                    'slug' => $producto->categoria->slug,
                    'padre' => $producto->categoria->padre ? [
                        'nombre' => $producto->categoria->padre->nombre,
                        'slug' => $producto->categoria->padre->slug,
                    ] : null,
                ] : null,
                'marca' => $producto->marca ? [
                    'nombre' => $producto->marca->nombre,
                    'slug' => $producto->marca->slug,
                ] : null,
            ],
            'relacionados' => $this->tarjetas($relacionadosBase),
            'metodosPago' => $this->metodosPagoPublicos(),
            'envios' => $this->config->string('politicas.envios') !== null,
            'seo' => $this->seoProducto($producto, $precio, $disponibilidad, $imagenes, $descripcionMeta ?: null)->toArray(),
        ]);
    }

    // ------------------------------------------------------------------ Páginas informativas

    public function pagina(string $slug): Response
    {
        $pagina = self::PAGINAS[$slug] ?? null;
        abort_if($pagina === null, 404);

        $contenido = $this->config->string($pagina['clave']);
        abort_if($contenido === null, 404);

        return Inertia::render('Tienda/Pagina', [
            'pagina' => [
                'slug' => $slug,
                'titulo' => $pagina['titulo'],
                'contenido' => $contenido,
            ],
            'seo' => Seo::nuevo()
                ->titulo($pagina['titulo'])
                ->descripcion($contenido)
                ->canonical(route('tienda.pagina', $slug, false))
                ->tipo('article')
                ->conMigas([
                    ['nombre' => 'Inicio', 'url' => route('home', [], false)],
                    ['nombre' => $pagina['titulo'], 'url' => route('tienda.pagina', $slug, false)],
                ])
                ->toArray(),
        ]);
    }

    public function preguntas(): Response
    {
        $faq = $this->faq();
        abort_if($faq->isEmpty(), 404);

        return Inertia::render('Tienda/Preguntas', [
            'faq' => $faq,
            'seo' => Seo::nuevo()
                ->titulo('Preguntas frecuentes')
                ->descripcion('Preguntas frecuentes de la tienda '.Seo::sitio().': '.$faq->pluck('pregunta')->implode(' '))
                ->canonical(route('tienda.preguntas', [], false))
                ->tipo('article')
                ->jsonLd([
                    '@type' => 'FAQPage',
                    'mainEntity' => $faq->map(fn (array $f) => [
                        '@type' => 'Question',
                        'name' => $f['pregunta'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['respuesta']],
                    ])->all(),
                ])
                ->toArray(),
        ]);
    }

    // ------------------------------------------------------------------ Sitemap y robots

    /**
     * Sitemap con URL absolutas (APP_URL), lastmod e imagen principal de cada producto.
     */
    public function sitemap(): HttpResponse
    {
        $ultimoProducto = Producto::query()->publicados()->max('updated_at');
        $lastmodGeneral = $ultimoProducto ? Carbon::parse($ultimoProducto)->toAtomString() : null;

        $urls = collect([
            ['loc' => Seo::absoluta(route('home', [], false)), 'lastmod' => $lastmodGeneral, 'priority' => '1.0', 'imagenes' => []],
            ['loc' => Seo::absoluta(route('tienda.catalogo', [], false)), 'lastmod' => $lastmodGeneral, 'priority' => '0.9', 'imagenes' => []],
        ]);

        Categoria::query()
            ->where('activa', true)
            ->whereHas('productos', fn (Builder $q) => $q->publicados())
            ->orderBy('orden')
            ->get(['id', 'slug', 'imagen', 'updated_at'])
            ->each(fn (Categoria $c) => $urls->push([
                'loc' => Seo::absoluta(route('tienda.catalogo', ['categoria' => $c->slug], false)),
                'lastmod' => $c->updated_at?->toAtomString(),
                'priority' => '0.7',
                'imagenes' => array_filter([ImagenUrl::resolver($c->imagen)]),
            ]));

        Producto::query()
            ->publicados()
            ->select(['id', 'slug', 'nombre', 'imagen_principal', 'updated_at'])
            ->with('imagenes:id,producto_id,ruta,orden,principal')
            ->lazyById(500)
            ->each(fn (Producto $p) => $urls->push([
                'loc' => Seo::absoluta(route('tienda.show', $p->slug, false)),
                'lastmod' => $p->updated_at?->toAtomString(),
                'priority' => '0.8',
                'imagenes' => array_filter([$p->imagenPrincipalUrl()]),
            ]));

        foreach ($this->config->enlaces() as $enlace) {
            $urls->push(['loc' => Seo::absoluta($enlace['url']), 'lastmod' => null, 'priority' => '0.3', 'imagenes' => []]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>'
                .($u['lastmod'] ? '<lastmod>'.e($u['lastmod']).'</lastmod>' : '')
                .'<priority>'.$u['priority'].'</priority>';

            foreach ($u['imagenes'] as $imagen) {
                $xml .= '<image:image><image:loc>'.e(Seo::absoluta($imagen)).'</image:loc></image:image>';
            }

            $xml .= '</url>'."\n";
        }

        $xml .= '</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * robots.txt dinámico: en producción (config `seo.indexar`) bloquea áreas privadas y anuncia
     * el sitemap con APP_URL; en cualquier otro entorno (p. ej. pruebas) bloquea todo el sitio.
     */
    public function robots(): HttpResponse
    {
        if (! config('seo.indexar')) {
            $texto = "# Entorno no productivo: no indexar.\nUser-agent: *\nDisallow: /\n";
        } else {
            $texto = implode("\n", [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /vendedor',
                'Disallow: /checkout',
                'Disallow: /mi-cuenta',
                'Disallow: /carrito',
                'Disallow: /dashboard',
                'Disallow: /settings',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /reset-password',
                'Disallow: /buscar/',
                'Disallow: /*?*q=',
                '',
                'Sitemap: '.Seo::absoluta(route('tienda.sitemap', [], false)),
                '',
            ]);
        }

        return response($texto, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    // ================================================================== Internos

    /** Consulta base de productos visibles al público con relaciones para tarjetas. */
    private function consultaPublica(): Builder
    {
        return Producto::query()
            ->publicados()
            ->with([
                'categoria:id,nombre,slug',
                'marca:id,nombre,slug',
                'imagenes:id,producto_id,ruta,orden,principal',
                'ofertas',
            ]);
    }

    /**
     * @param  EloquentCollection<int, Producto>  $productos
     * @return Collection<int, array<string, mixed>>
     */
    private function tarjetas(EloquentCollection $productos): Collection
    {
        if ($productos->isEmpty()) {
            return collect();
        }

        $precios = $this->precios->calcularMuchos($productos);

        return $productos->map(fn (Producto $p) => $this->tarjeta($p, $precios->get($p->id)))->values();
    }

    /** @param  array<string, mixed>|null  $precio */
    private function tarjeta(Producto $p, ?array $precio): array
    {
        $disponibilidad = $this->disponibilidad($p);

        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'slug' => $p->slug,
            'sku' => $p->sku,
            'modelo' => $p->modelo,
            'imagen' => $p->imagenPrincipalUrl(),
            'precio_original' => $precio['precio_original'] ?? null,
            'precio_final' => $precio['precio_final'] ?? null,
            'descuento' => $precio['descuento'] ?? 0,
            'tiene_oferta' => (bool) ($precio['tiene_oferta'] ?? false),
            'oferta_nombre' => $precio['oferta']['nombre'] ?? null,
            'disponibilidad' => $disponibilidad,
            'stock' => $disponibilidad === 'en_stock' ? (int) $p->stock : null,
            'tiempo_entrega_dias' => $p->esBajoPedido() ? $p->tiempo_entrega_dias : null,
            'destacado' => (bool) $p->destacado,
            'nuevo' => $p->publicado_en !== null && $p->publicado_en->gt(now()->subDays(30)),
            'categoria' => $p->categoria ? ['nombre' => $p->categoria->nombre, 'slug' => $p->categoria->slug] : null,
            'marca' => $p->marca ? ['nombre' => $p->marca->nombre, 'slug' => $p->marca->slug] : null,
        ];
    }

    private function disponibilidad(Producto $p): string
    {
        if ($p->modalidad_venta === ModalidadVenta::BajoPedido->value) {
            return 'bajo_pedido';
        }

        return (int) $p->stock > 0 ? 'en_stock' : 'agotado';
    }

    /** @return array{q: ?string, categoria: ?string, marca: ?string, disponibilidad: ?string, precio_min: ?float, precio_max: ?float, orden: string} */
    private function filtrosDesdeRequest(Request $request): array
    {
        $q = $this->limpiarBusqueda($request->input('q', $request->input('buscar')));

        $slug = fn ($v) => is_string($v) && preg_match('/^[a-z0-9-]{1,170}$/', $v) ? $v : null;

        $disponibilidad = $request->input('disponibilidad');
        $orden = $request->input('orden');
        $min = $request->input('precio_min');
        $max = $request->input('precio_max');

        $min = is_numeric($min) && (float) $min >= 0 ? round((float) $min, 2) : null;
        $max = is_numeric($max) && (float) $max > 0 ? round((float) $max, 2) : null;

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [
            'q' => $q,
            'categoria' => $slug($request->input('categoria')),
            'marca' => $slug($request->input('marca')),
            'disponibilidad' => in_array($disponibilidad, self::DISPONIBILIDADES, true) ? $disponibilidad : null,
            'precio_min' => $min,
            'precio_max' => $max,
            'orden' => in_array($orden, self::ORDENES, true) ? $orden : 'relevancia',
        ];
    }

    private function aplicarFiltros(Builder $consulta, array $filtros): void
    {
        if ($filtros['q'] !== null) {
            $this->aplicarBusqueda($consulta, $filtros['q']);
        }

        if ($filtros['categoria'] !== null) {
            $categoria = Categoria::query()->where('slug', $filtros['categoria'])->where('activa', true)->first(['id']);
            $ids = $categoria
                ? Categoria::query()->where('categoria_padre_id', $categoria->id)->where('activa', true)->pluck('id')->push($categoria->id)->all()
                : [0];
            $consulta->whereIn('productos.categoria_id', $ids);
        }

        if ($filtros['marca'] !== null) {
            $consulta->whereHas('marca', fn (Builder $m) => $m->where('slug', $filtros['marca']));
        }

        match ($filtros['disponibilidad']) {
            'en_stock' => $consulta->where('productos.modalidad_venta', '!=', ModalidadVenta::BajoPedido->value)->where('productos.stock', '>', 0),
            'bajo_pedido' => $consulta->where('productos.modalidad_venta', ModalidadVenta::BajoPedido->value),
            'agotado' => $consulta->where('productos.modalidad_venta', '!=', ModalidadVenta::BajoPedido->value)->where('productos.stock', '<=', 0),
            default => null,
        };

        if ($filtros['precio_min'] !== null) {
            $consulta->where('productos.precio', '>=', $filtros['precio_min']);
        }

        if ($filtros['precio_max'] !== null) {
            $consulta->where('productos.precio', '<=', $filtros['precio_max']);
        }
    }

    private function aplicarOrden(Builder $consulta, array $filtros): void
    {
        match ($filtros['orden']) {
            'precio_asc' => $consulta->orderBy('productos.precio'),
            'precio_desc' => $consulta->orderByDesc('productos.precio'),
            'nuevos' => $consulta->orderByDesc('productos.publicado_en'),
            'nombre' => $consulta->orderBy('productos.nombre'),
            default => $filtros['q'] !== null
                ? $this->ordenarPorRelevancia($consulta, $filtros['q'])
                : $consulta->orderByDesc('productos.destacado')->orderByDesc('productos.publicado_en'),
        };

        $consulta->orderByDesc('productos.id');
    }

    private function aplicarBusqueda(Builder $consulta, string $q): void
    {
        $terminos = collect(preg_split('/\s+/u', $q) ?: [])->filter()->take(6);

        foreach ($terminos as $termino) {
            $like = '%'.$this->escaparLike($termino).'%';

            $consulta->where(function (Builder $w) use ($like) {
                $w->whereRaw("productos.nombre LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("productos.sku LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("productos.modelo LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("productos.descripcion LIKE ? ESCAPE '!'", [$like]);
            });
        }
    }

    private function ordenarPorRelevancia(Builder $consulta, string $q): Builder
    {
        $escapado = $this->escaparLike($q);

        return $consulta->orderByRaw(
            "CASE WHEN productos.sku = ? THEN 0 WHEN productos.nombre LIKE ? ESCAPE '!' THEN 1 WHEN productos.nombre LIKE ? ESCAPE '!' THEN 2 WHEN productos.modelo LIKE ? ESCAPE '!' THEN 3 ELSE 4 END",
            [$q, $escapado.'%', '%'.$escapado.'%', '%'.$escapado.'%']
        );
    }

    private function limpiarBusqueda(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return $valor === '' ? null : mb_substr($valor, 0, 80);
    }

    private function escaparLike(string $valor): string
    {
        // Carácter de escape '!' (portátil entre SQLite y MariaDB, sin ambigüedad con '\').
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $valor);
    }

    /** Categorías activas con al menos un producto publicado (con conteo). */
    private function categoriasConProductos(): Collection
    {
        return Categoria::query()
            ->where('activa', true)
            ->withCount(['productos as productos_count' => fn (Builder $q) => $q->publicados()])
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'slug', 'imagen', 'categoria_padre_id', 'orden'])
            ->filter(fn (Categoria $c) => $c->productos_count > 0)
            ->map(fn (Categoria $c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'slug' => $c->slug,
                'imagen' => ImagenUrl::resolver($c->imagen),
                'total' => (int) $c->productos_count,
            ])
            ->values();
    }

    private function marcasConProductos(): Collection
    {
        return Marca::query()
            ->where('activa', true)
            ->withCount(['productos as productos_count' => fn (Builder $q) => $q->publicados()])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'slug'])
            ->filter(fn (Marca $m) => $m->productos_count > 0)
            ->map(fn (Marca $m) => ['nombre' => $m->nombre, 'slug' => $m->slug, 'total' => (int) $m->productos_count])
            ->values();
    }

    private function banners(): Collection
    {
        return Banner::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereNull('inicia_en')->orWhere('inicia_en', '<=', now()))
            ->where(fn ($q) => $q->whereNull('termina_en')->orWhere('termina_en', '>=', now()))
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['id', 'titulo', 'descripcion', 'imagen', 'url'])
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'titulo' => $b->titulo,
                'descripcion' => $b->descripcion,
                'imagen' => ImagenUrl::resolver($b->imagen),
                'url' => $this->urlSegura($b->url),
            ])
            ->filter(fn (array $b) => $b['imagen'] !== null)
            ->values();
    }

    /** Sólo URLs relativas o http(s); nada de javascript:, data:, etc. */
    private function urlSegura(?string $url): ?string
    {
        $url = $url !== null ? trim($url) : null;

        if ($url === null || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) ? $url : null;
    }

    private function productosEnOferta(int $limite): Collection
    {
        $ofertas = Oferta::query()->activas()->get(['id', 'aplica_a', 'categoria_id', 'marca_id']);

        if ($ofertas->isEmpty()) {
            return collect();
        }

        $categorias = $ofertas->where('aplica_a', 'categoria')->pluck('categoria_id')->filter()->unique()->values()->all();
        $marcas = $ofertas->where('aplica_a', 'marca')->pluck('marca_id')->filter()->unique()->values()->all();
        $directas = $ofertas->pluck('id')->all();

        $candidatos = $this->consultaPublica()
            ->where(function (Builder $w) use ($categorias, $marcas, $directas) {
                $w->whereHas('ofertas', fn (Builder $o) => $o->whereIn('ofertas.id', $directas));

                if ($categorias !== []) {
                    $w->orWhereIn('productos.categoria_id', $categorias);
                }

                if ($marcas !== []) {
                    $w->orWhereIn('productos.marca_id', $marcas);
                }
            })
            ->orderByDesc('productos.destacado')
            ->orderByDesc('productos.publicado_en')
            ->take($limite * 3)
            ->get(self::COLUMNAS_TARJETA);

        return $this->tarjetas($candidatos)
            ->filter(fn (array $t) => $t['tiene_oferta'])
            ->sortByDesc(fn (array $t) => $t['precio_original'] ? $t['descuento'] / $t['precio_original'] : 0)
            ->take($limite)
            ->values();
    }

    /** Métodos de pago activos (sin tarjeta mientras los pagos en línea estén deshabilitados). */
    private function metodosPagoPublicos(): Collection
    {
        return MetodoPago::query()
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'nombre', 'clave'])
            ->reject(fn (MetodoPago $m) => $m->clave === MetodoPagoClave::Openpay->value && ! config('payments.enabled'))
            ->map(fn (MetodoPago $m) => [
                'clave' => $m->clave,
                'nombre' => $m->nombre ?: (MetodoPagoClave::tryFrom((string) $m->clave)?->label() ?? $m->clave),
            ])
            ->values();
    }

    /** Beneficios administrables o, si no hay, sólo hechos verdaderos del sistema. */
    private function beneficios(): Collection
    {
        $configurados = collect($this->config->array('home.beneficios'))
            ->filter(fn ($b) => is_array($b) && filled($b['titulo'] ?? null))
            ->map(fn (array $b) => [
                'titulo' => (string) $b['titulo'],
                'descripcion' => filled($b['descripcion'] ?? null) ? (string) $b['descripcion'] : null,
                'icono' => filled($b['icono'] ?? null) ? (string) $b['icono'] : null,
            ])
            ->values();

        if ($configurados->isNotEmpty()) {
            return $configurados;
        }

        $claves = $this->metodosPagoPublicos()->pluck('clave');
        $beneficios = collect();

        $transferencia = $claves->contains(MetodoPagoClave::Transferencia->value);
        $efectivo = $claves->contains(MetodoPagoClave::EfectivoPresencial->value) || $claves->contains(MetodoPagoClave::EfectivoContraEntrega->value);

        if ($transferencia || $efectivo) {
            $beneficios->push([
                'titulo' => match (true) {
                    $transferencia && $efectivo => 'Paga por transferencia o en efectivo',
                    $transferencia => 'Paga por transferencia bancaria',
                    default => 'Paga en efectivo',
                },
                'descripcion' => 'Sin necesidad de tarjeta.',
                'icono' => 'wallet',
            ]);
        }

        $beneficios->push([
            'titulo' => 'Seguimiento de tu pedido en línea',
            'descripcion' => 'Consulta el estado de tu compra desde tu cuenta.',
            'icono' => 'package-search',
        ]);

        return $beneficios;
    }

    private function faq(): Collection
    {
        return collect($this->config->array('home.faq'))
            ->filter(fn ($f) => is_array($f) && filled($f['pregunta'] ?? null) && filled($f['respuesta'] ?? null))
            ->map(fn (array $f) => ['pregunta' => (string) $f['pregunta'], 'respuesta' => (string) $f['respuesta']])
            ->values();
    }

    // ------------------------------------------------------------------ SEO

    /**
     * Título, descripción y canonical del catálogo. El canonical sólo conserva la categoría y la
     * página (los demás filtros/orden apuntan a la versión sin filtrar); búsquedas y listados
     * vacíos llevan noindex.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function seoCatalogo(array $filtros, ?Categoria $categoria, int $pagina, int $total): Seo
    {
        $parametros = array_filter([
            'categoria' => $categoria?->slug,
            'page' => $pagina > 1 ? $pagina : null,
        ]);

        $titulo = $filtros['q'] !== null
            ? 'Resultados de búsqueda' // nunca texto del usuario en <title> (noindex de todos modos)
            : ($categoria?->nombre ?? 'Catálogo');

        if ($pagina > 1) {
            $titulo .= ' · Página '.$pagina;
        }

        $descripcion = $categoria
            ? ($categoria->descripcion ?: $categoria->nombre.' en la tienda '.Seo::sitio().': '.$total.' '.($total === 1 ? 'producto' : 'productos').' con precio en pesos mexicanos. Compra en línea y sigue tu pedido.')
            : 'Explora '.$total.' '.($total === 1 ? 'producto' : 'productos').' en la tienda '.Seo::sitio().': filtra por categoría, marca, precio y disponibilidad.';

        $migas = [
            ['nombre' => 'Inicio', 'url' => route('home', [], false)],
            ['nombre' => 'Catálogo', 'url' => route('tienda.catalogo', [], false)],
        ];

        if ($categoria) {
            $migas[] = ['nombre' => $categoria->nombre, 'url' => route('tienda.catalogo', ['categoria' => $categoria->slug], false)];
        }

        return Seo::nuevo()
            ->titulo($titulo)
            ->descripcion($descripcion)
            ->canonical(route('tienda.catalogo', $parametros, false))
            ->noindex($filtros['q'] !== null || $total === 0)
            ->conMigas($migas);
    }

    /**
     * Metadatos del producto: imagen OG derivada (1200x630) con respaldo a la foto principal
     * JPG/PNG o a la imagen general; precio/disponibilidad y JSON-LD Product + BreadcrumbList.
     *
     * @param  array<string, mixed>  $precio
     * @param  Collection<int, string>  $imagenes
     */
    private function seoProducto(Producto $producto, array $precio, string $disponibilidad, Collection $imagenes, ?string $descripcion): Seo
    {
        $url = route('tienda.show', $producto->slug, false);
        $titulo = $producto->meta_titulo ?: $producto->nombre;
        $precioFinal = isset($precio['precio_final']) && (float) $precio['precio_final'] > 0 ? (float) $precio['precio_final'] : null;

        $seo = Seo::nuevo()
            ->titulo($titulo)
            ->descripcion($descripcion)
            ->canonical($url)
            ->tipo('product')
            ->producto($precioFinal, $disponibilidad);

        $og = $this->imagenOg->para($producto, $imagenes->isNotEmpty() ? null : $producto->imagen_principal);
        $primera = $imagenes->first();

        if ($og !== null) {
            $seo->imagen($og['url'], $og['ancho'], $og['alto'], $og['tipo'], $producto->nombre);
        } elseif ($primera && preg_match('/\.(jpe?g|png)$/i', (string) parse_url($primera, PHP_URL_PATH), $m)) {
            $seo->imagen($primera, null, null, strtolower($m[1]) === 'png' ? 'image/png' : 'image/jpeg', $producto->nombre);
        }

        $nodo = array_filter([
            '@type' => 'Product',
            '@id' => Seo::absoluta($url).'#producto',
            'name' => $producto->nombre,
            'description' => $descripcion,
            'sku' => $producto->sku ?: null,
            'image' => $imagenes->map(fn (string $i) => Seo::absoluta($i))->values()->all() ?: null,
            'url' => Seo::absoluta($url),
            'category' => $producto->categoria?->nombre,
            'brand' => $producto->marca ? ['@type' => 'Brand', 'name' => $producto->marca->nombre] : null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($precioFinal !== null) {
            $nodo['offers'] = array_filter([
                '@type' => 'Offer',
                'url' => Seo::absoluta($url),
                'price' => number_format($precioFinal, 2, '.', ''),
                'priceCurrency' => (string) config('seo.moneda', 'MXN'),
                'availability' => match ($disponibilidad) {
                    'en_stock' => 'https://schema.org/InStock',
                    'bajo_pedido' => 'https://schema.org/PreOrder',
                    default => 'https://schema.org/OutOfStock',
                },
                'priceValidUntil' => ! empty($precio['oferta']['termina_en'])
                    ? Carbon::parse($precio['oferta']['termina_en'])->toDateString()
                    : null,
                'seller' => ['@type' => 'Organization', 'name' => Seo::sitio()],
            ], fn ($v) => $v !== null);
        }

        $migas = [
            ['nombre' => 'Inicio', 'url' => route('home', [], false)],
            ['nombre' => 'Catálogo', 'url' => route('tienda.catalogo', [], false)],
        ];

        if ($producto->categoria?->padre) {
            $migas[] = ['nombre' => $producto->categoria->padre->nombre, 'url' => route('tienda.catalogo', ['categoria' => $producto->categoria->padre->slug], false)];
        }

        if ($producto->categoria) {
            $migas[] = ['nombre' => $producto->categoria->nombre, 'url' => route('tienda.catalogo', ['categoria' => $producto->categoria->slug], false)];
        }

        $migas[] = ['nombre' => $producto->nombre, 'url' => $url];

        return $seo->jsonLd($nodo)->conMigas($migas);
    }
}
