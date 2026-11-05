<?php

namespace App\Support;

use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * Metadatos SEO / Open Graph de una página.
 *
 * Los controladores públicos construyen uno y lo envían como prop `seo`; el middleware
 * comparte uno predeterminado para el resto. `resources/views/app.blade.php` lo renderiza en
 * el HTML del servidor (los crawlers de WhatsApp, Facebook, X… no ejecutan JavaScript) y
 * `SeoHead.vue` mantiene las mismas etiquetas (mismo `data-inertia`) al navegar en el cliente.
 *
 * Todas las URL se hacen absolutas con `config('app.url')` (nunca dominios fijos).
 *
 * @implements Arrayable<string, mixed>
 */
final class Seo implements Arrayable
{
    /** Rutas públicas que pueden indexarse (todas las demás llevan noindex). */
    public const RUTAS_INDEXABLES = ['home', 'tienda.catalogo', 'tienda.show', 'tienda.pagina', 'tienda.preguntas'];

    /** Disponibilidad interna → [schema.org, og product:availability]. */
    private const DISPONIBILIDAD = [
        'en_stock' => ['https://schema.org/InStock', 'in stock'],
        'bajo_pedido' => ['https://schema.org/PreOrder', 'preorder'],
        'agotado' => ['https://schema.org/OutOfStock', 'out of stock'],
    ];

    private ?string $titulo = null;

    private ?string $tituloSocial = null;

    private ?string $tituloDocumento = null;

    private ?string $descripcion = null;

    private ?string $canonical = null;

    private bool $noindex = false;

    private string $tipo = 'website';

    /** @var array{url: string, ancho: ?int, alto: ?int, tipo: ?string, alt: ?string}|null */
    private ?array $imagen = null;

    /** @var array{precio: string, moneda: string, disponibilidad: string}|null */
    private ?array $producto = null;

    /** @var list<array<string, mixed>> */
    private array $jsonLd = [];

    public static function nuevo(): self
    {
        return new self;
    }

    /** Metadatos por defecto de la petición actual (sin datos específicos de página). */
    public static function predeterminado(?string $ruta = null): self
    {
        $seo = new self;
        $seo->noindex = ! in_array($ruta, self::RUTAS_INDEXABLES, true);

        return $seo;
    }

    // ------------------------------------------------------------------ setters

    public function titulo(?string $titulo): self
    {
        $this->titulo = self::limpiar($titulo, 70, false);

        return $this;
    }

    /** <title> completo, sin el sufijo de marca (p. ej. el home: "Mr Lana | Tienda en línea…"). */
    public function tituloDocumento(?string $titulo): self
    {
        $this->tituloDocumento = self::limpiar($titulo, 90, false);

        return $this;
    }

    /** Título para redes (og:title / twitter:title) si debe diferir del <title>. */
    public function tituloSocial(?string $titulo): self
    {
        $this->tituloSocial = self::limpiar($titulo, 95, false);

        return $this;
    }

    public function descripcion(?string $descripcion): self
    {
        $this->descripcion = self::limpiar($descripcion, 160);

        return $this;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url !== null ? self::absoluta($url) : null;

        return $this;
    }

    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    public function tipo(string $tipo): self
    {
        $this->tipo = in_array($tipo, ['website', 'product', 'article'], true) ? $tipo : 'website';

        return $this;
    }

    public function imagen(?string $url, ?int $ancho = null, ?int $alto = null, ?string $tipo = null, ?string $alt = null): self
    {
        $this->imagen = filled($url) ? [
            'url' => self::absoluta((string) $url),
            'ancho' => $ancho,
            'alto' => $alto,
            'tipo' => $tipo,
            'alt' => self::limpiar($alt, 200, false),
        ] : null;

        return $this;
    }

    /** Precio publicado (sólo si es > 0) y disponibilidad (`en_stock|bajo_pedido|agotado`). */
    public function producto(?float $precio, string $disponibilidad): self
    {
        $this->producto = $precio !== null && $precio > 0 && isset(self::DISPONIBILIDAD[$disponibilidad]) ? [
            'precio' => number_format($precio, 2, '.', ''),
            'moneda' => (string) config('seo.moneda', 'MXN'),
            'disponibilidad' => self::DISPONIBILIDAD[$disponibilidad][1],
        ] : null;

        return $this;
    }

    /** @param  array<string, mixed>  $nodo  nodo schema.org (sin @context) */
    public function jsonLd(array $nodo): self
    {
        $this->jsonLd[] = $nodo;

        return $this;
    }

    // ------------------------------------------------------------------ JSON-LD

    /**
     * Organization/OnlineStore + WebSite (con SearchAction al catálogo).
     *
     * `name` + `alternateName` del WebSite es lo que Google usa para el "nombre del sitio" en
     * los resultados; así "mr lana" y "tienda mr lana" se asocian a la misma entidad.
     */
    public function conOrganizacion(): self
    {
        $config = app(ConfiguracionTienda::class);
        $base = self::base();

        $redes = array_values(array_filter(
            $config->redes(),
            fn ($url) => is_string($url) && preg_match('#^https?://#i', $url),
        ));

        $contacto = $config->contacto();
        $organizacion = array_filter([
            '@type' => ['Organization', 'OnlineStore'],
            '@id' => $base.'/#organizacion',
            'name' => self::sitio(),
            'alternateName' => self::nombresAlternos() ?: null,
            'description' => (string) config('seo.descripcion'),
            'url' => $base.'/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => self::absoluta('/icons/icon-512.png'),
                'width' => 512,
                'height' => 512,
            ],
            'image' => self::absoluta((string) config('seo.imagen.ruta')),
            'email' => $contacto['correo'] ?? null,
            'telephone' => $contacto['telefono'] ?? null,
            'sameAs' => $redes ?: null,
        ], fn ($v) => $v !== null);

        $this->jsonLd($organizacion);

        $this->jsonLd(array_filter([
            '@type' => 'WebSite',
            '@id' => $base.'/#sitio',
            'name' => self::sitio(),
            'alternateName' => self::nombresAlternos() ?: null,
            'url' => $base.'/',
            'inLanguage' => 'es-MX',
            'publisher' => ['@id' => $base.'/#organizacion'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => self::absoluta(route('tienda.catalogo', [], false)).'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ], fn ($v) => $v !== null));

        return $this;
    }

    /** @param  list<array{nombre: string, url: string}>  $migas */
    public function conMigas(array $migas): self
    {
        if ($migas === []) {
            return $this;
        }

        $this->jsonLd([
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $miga, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $miga['nombre'],
                'item' => self::absoluta($miga['url']),
            ], $migas, array_keys($migas)),
        ]);

        return $this;
    }

    // ------------------------------------------------------------------ salida

    /**
     * @return array{
     *     titulo: ?string, titulo_documento: string, titulo_social: string, descripcion: string,
     *     canonical: ?string, robots: string, tipo: string, sitio: string, locale: string,
     *     imagen: array{url: string, secure_url: ?string, ancho: ?int, alto: ?int, tipo: ?string, alt: string},
     *     producto: array{precio: string, moneda: string, disponibilidad: string}|null,
     *     verificacion: array{google: ?string, bing: ?string},
     *     json_ld: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        $sitio = self::sitio();
        $nombreApp = (string) config('app.name', $sitio);
        $verificacion = (array) config('seo.verificacion', []);

        $descripcion = $this->descripcion
            ?? self::limpiar(app(ConfiguracionTienda::class)->string('seo.descripcion'), 160)
            ?? (string) config('seo.descripcion');

        $imagen = $this->imagen ?? [
            'url' => self::absoluta((string) config('seo.imagen.ruta')),
            'ancho' => (int) config('seo.imagen.ancho'),
            'alto' => (int) config('seo.imagen.alto'),
            'tipo' => (string) config('seo.imagen.tipo'),
            'alt' => null,
        ];

        $imagen['secure_url'] = str_starts_with($imagen['url'], 'https://') ? $imagen['url'] : null;
        $imagen['alt'] = $imagen['alt'] ?? ($this->titulo ?? $sitio).' — '.$sitio;

        $robots = match (true) {
            ! config('seo.indexar') => 'noindex, nofollow',
            $this->noindex => 'noindex, follow',
            default => 'index, follow, max-image-preview:large',
        };

        return [
            'titulo' => $this->titulo,
            // Igual que el callback `title` de resources/js/app.ts ("<título> - APP_NAME").
            // Patrón "{Página} | Mr Lana Tienda"; el home define el suyo completo con tituloDocumento().
            'titulo_documento' => $this->tituloDocumento
                ?? ($this->titulo ? $this->titulo.' | '.$sitio.' Tienda' : (string) config('seo.titulo_home', $nombreApp)),
            'titulo_social' => $this->tituloSocial ?? $this->titulo ?? $sitio,
            'descripcion' => $descripcion,
            'canonical' => $this->canonical,
            'robots' => $robots,
            'tipo' => $this->producto !== null ? 'product' : $this->tipo,
            'sitio' => $sitio,
            'locale' => (string) config('seo.locale', 'es_MX'),
            'imagen' => $imagen,
            'producto' => $this->producto,
            'verificacion' => [
                'google' => filled($verificacion['google'] ?? null) ? (string) $verificacion['google'] : null,
                'bing' => filled($verificacion['bing'] ?? null) ? (string) $verificacion['bing'] : null,
            ],
            'json_ld' => array_map(fn (array $nodo) => ['@context' => 'https://schema.org'] + $nodo, $this->jsonLd),
        ];
    }

    // ------------------------------------------------------------------ utilidades

    /** Raíz absoluta del sitio desde APP_URL (sin "/" final). */
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function sitio(): string
    {
        return (string) config('seo.sitio', 'Mr Lana');
    }

    /** @return list<string> Variantes con que la gente busca la marca ("Tienda Mr Lana", "MrLana"…). */
    public static function nombresAlternos(): array
    {
        return array_values(array_filter((array) config('seo.nombres_alternos', []), 'is_string'));
    }

    /**
     * Hace absoluta una URL con APP_URL. Las URL del propio sitio (generadas con el host de la
     * petición) se reescriben a APP_URL; las de otros dominios (CDN) se respetan.
     */
    public static function absoluta(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return self::base().'/';
        }

        if (preg_match('#^https?://#i', $url)) {
            $host = parse_url($url, PHP_URL_HOST);
            $propios = array_filter([
                parse_url(self::base(), PHP_URL_HOST),
                request()->getHost(),
            ]);

            if (! in_array($host, $propios, true)) {
                return $url;
            }

            $ruta = (string) parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $url = ($ruta === '' ? '/' : $ruta).($query ? '?'.$query : '');
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return self::base().'/'.ltrim($url, '/');
    }

    /** Texto plano en una sola línea, recortado (`$html`: el origen puede traer etiquetas, p. ej. descripciones). */
    public static function limpiar(?string $texto, int $limite, bool $html = true): ?string
    {
        if ($texto === null) {
            return null;
        }

        if ($html) {
            $texto = strip_tags(html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));

        return $texto === '' ? null : Str::limit($texto, $limite - 1, '…');
    }
}
