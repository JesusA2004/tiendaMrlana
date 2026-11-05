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

    'locale' => 'es_MX',

    'moneda' => 'MXN',

    'descripcion' => 'Tienda en línea Mr Lana: muebles y artículos para el hogar. Compra en línea y paga por transferencia o en efectivo.',

];
