<!DOCTYPE html>
<html lang="es-MX" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Detecta el modo oscuro del sistema antes de pintar --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        <style>
            html {
                background-color: #ffffff;
            }

            html.dark {
                background-color: #0b1110;
            }
        </style>

        {{-- Identidad / PWA --}}
        <meta name="theme-color" content="#73C83A" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#14332B" media="(prefers-color-scheme: dark)">
        <meta name="application-name" content="Mr Lana Tienda">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Mr Lana">
        <meta name="format-detection" content="telephone=no">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">

        {{-- Tipografías de marca: Baloo 2 (títulos) y Montserrat (texto) --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

        {{--
            SEO y vistas previas al compartir (WhatsApp, Facebook, X, Telegram, iMessage): los crawlers
            no ejecutan JavaScript, así que las metaetiquetas salen del servidor a partir de la prop `seo`
            (App\Support\Seo). SeoHead.vue usa las mismas claves `data-inertia` para reemplazarlas al
            navegar en el cliente sin duplicarlas. Blade escapa todo con e().
        --}}
        @php($seo = is_array($page['props']['seo'] ?? null) ? $page['props']['seo'] : \App\Support\Seo::predeterminado(request()->route()?->getName())->toArray())
        <x-inertia::head>
            <title data-inertia="">{{ $seo['titulo_documento'] }}</title>
            <meta name="description" data-inertia="description" content="{{ $seo['descripcion'] }}">
            <meta name="robots" data-inertia="robots" content="{{ $seo['robots'] }}">
            @if ($seo['canonical'])
                <link rel="canonical" data-inertia="canonical" href="{{ $seo['canonical'] }}">
                <meta property="og:url" data-inertia="og:url" content="{{ $seo['canonical'] }}">
            @endif
            <meta property="og:site_name" data-inertia="og:site_name" content="{{ $seo['sitio'] }}">
            <meta property="og:locale" data-inertia="og:locale" content="{{ $seo['locale'] }}">
            <meta property="og:type" data-inertia="og:type" content="{{ $seo['tipo'] }}">
            <meta property="og:title" data-inertia="og:title" content="{{ $seo['titulo_social'] }}">
            <meta property="og:description" data-inertia="og:description" content="{{ $seo['descripcion'] }}">
            <meta property="og:image" data-inertia="og:image" content="{{ $seo['imagen']['url'] }}">
            @if ($seo['imagen']['secure_url'])
                <meta property="og:image:secure_url" data-inertia="og:image:secure_url" content="{{ $seo['imagen']['secure_url'] }}">
            @endif
            @if ($seo['imagen']['tipo'])
                <meta property="og:image:type" data-inertia="og:image:type" content="{{ $seo['imagen']['tipo'] }}">
            @endif
            @if ($seo['imagen']['ancho'] && $seo['imagen']['alto'])
                <meta property="og:image:width" data-inertia="og:image:width" content="{{ $seo['imagen']['ancho'] }}">
                <meta property="og:image:height" data-inertia="og:image:height" content="{{ $seo['imagen']['alto'] }}">
            @endif
            <meta property="og:image:alt" data-inertia="og:image:alt" content="{{ $seo['imagen']['alt'] }}">
            @if ($seo['producto'])
                <meta property="product:price:amount" data-inertia="product:price:amount" content="{{ $seo['producto']['precio'] }}">
                <meta property="product:price:currency" data-inertia="product:price:currency" content="{{ $seo['producto']['moneda'] }}">
                <meta property="product:availability" data-inertia="product:availability" content="{{ $seo['producto']['disponibilidad'] }}">
            @endif
            <meta name="twitter:card" data-inertia="twitter:card" content="summary_large_image">
            <meta name="twitter:title" data-inertia="twitter:title" content="{{ $seo['titulo_social'] }}">
            <meta name="twitter:description" data-inertia="twitter:description" content="{{ $seo['descripcion'] }}">
            <meta name="twitter:image" data-inertia="twitter:image" content="{{ $seo['imagen']['url'] }}">
            <meta name="twitter:image:alt" data-inertia="twitter:image:alt" content="{{ $seo['imagen']['alt'] }}">
        </x-inertia::head>
        {{-- Datos estructurados (sólo servidor; JSON_HEX_TAG evita cerrar el <script>). --}}
        @foreach ($seo['json_ld'] ?? [] as $nodo)
            <script type="application/ld+json">{!! json_encode($nodo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        <script>
            if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
                });
            }
        </script>
    </body>
</html>
