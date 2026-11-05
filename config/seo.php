<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Indexación
    |--------------------------------------------------------------------------
    | Sólo el sitio de producción debe indexarse. En cualquier otro entorno
    | (p. ej. pruebas.mr-lana.com) robots.txt responde "Disallow: /" y todas las
    | páginas llevan <meta name="robots" content="noindex, nofollow">.
    | Por defecto se indexa únicamente con APP_ENV=production; SEO_INDEXAR lo fuerza.
    */
    'indexar' => (bool) env('SEO_INDEXAR', env('APP_ENV') === 'production'),

    /*
    | Imagen Open Graph general (ruta pública, 1200x630 JPG < 300 KB).
    */
    'imagen' => [
        'ruta' => '/icons/og-image.jpg',
        'ancho' => 1200,
        'alto' => 630,
        'tipo' => 'image/jpeg',
    ],

    /*
    | Nombre de marca para og:site_name y JSON-LD (Organization / WebSite).
    */
    'sitio' => 'Mr Lana',

    /*
    | Variantes con que la gente busca la marca. Van en `alternateName` de Organization y
    | WebSite (JSON-LD) para que Google asocie "tienda mr lana", "mr. lana", etc. al sitio.
    */
    'nombres_alternos' => ['Tienda Mr Lana', 'Mr. Lana', 'MrLana', 'Mr Lana Tienda'],

    /*
    | <title> del home (el resto usa "{Página} | Mr Lana Tienda").
    */
    'titulo_home' => 'Mr Lana | Tienda en línea de muebles y artículos para el hogar',

    /*
    | Verificación de propiedad para Google Search Console y Bing Webmaster Tools
    | (método "etiqueta HTML": sólo el valor de content). Vacío = no se emite la meta.
    */
    'verificacion' => [
        'google' => env('SEO_GOOGLE_VERIFICATION'),
        'bing' => env('SEO_BING_VERIFICATION'),
    ],

    'locale' => 'es_MX',

    'moneda' => 'MXN',

    'descripcion' => 'Tienda Mr Lana: tienda en línea de muebles y artículos para el hogar. Explora el catálogo, compra en línea y paga por transferencia o en efectivo.',

];
