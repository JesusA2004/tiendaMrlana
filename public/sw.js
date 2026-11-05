/*
 * Service worker de Mr Lana Tienda.
 *
 * Reglas (docs/ARQUITECTURA.md §8):
 *  - Sólo se cachean recursos estáticos: /build (con hash), íconos, imágenes
 *    públicas y fuentes. Nunca HTML ni respuestas Inertia (JSON de páginas).
 *  - Nunca se intercepta POST/PUT/PATCH/DELETE.
 *  - Nunca se tocan /admin, /checkout, /mi-cuenta, /carrito, /vendedor, /login...
 *  - Si una navegación falla por falta de red se muestra /offline.html.
 */
const VERSION = 'mrlana-v1';
const STATIC_CACHE = `${VERSION}-static`;
const RUNTIME_CACHE = `${VERSION}-runtime`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.svg',
];

const PRIVATE_PREFIXES = [
    '/admin',
    '/checkout',
    '/mi-cuenta',
    '/carrito',
    '/vendedor',
    '/dashboard',
    '/settings',
    '/login',
    '/register',
    '/logout',
    '/user',
    '/two-factor',
    '/email',
    '/password',
];

const MAX_RUNTIME_ENTRIES = 80;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function isPrivatePath(pathname) {
    return PRIVATE_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
    );
}

function isStaticAsset(url) {
    if (url.origin === self.location.origin) {
        return (
            url.pathname.startsWith('/build/') ||
            url.pathname.startsWith('/icons/') ||
            url.pathname.startsWith('/img/') ||
            url.pathname.startsWith('/storage/') ||
            url.pathname === '/favicon.svg' ||
            url.pathname === '/favicon.ico' ||
            url.pathname === '/apple-touch-icon.png'
        );
    }

    return (
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com'
    );
}

async function trimCache(cacheName, max) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();

    if (keys.length > max) {
        await Promise.all(
            keys.slice(0, keys.length - max).map((key) => cache.delete(key)),
        );
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (
        response.ok &&
        (response.type === 'basic' || response.type === 'cors')
    ) {
        const cache = await caches.open(RUNTIME_CACHE);
        cache.put(request, response.clone());
        trimCache(RUNTIME_CACHE, MAX_RUNTIME_ENTRIES);
    }

    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Navegaciones (HTML): siempre red; sin red → página offline. Nunca se cachean.
    if (request.mode === 'navigate') {
        if (url.origin !== self.location.origin) {
            return;
        }

        event.respondWith(
            fetch(request).catch(async () => {
                const offline = await caches.match(OFFLINE_URL);

                return (
                    offline ||
                    new Response('Sin conexión', {
                        status: 503,
                        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                    })
                );
            }),
        );

        return;
    }

    // Peticiones Inertia/JSON o rutas privadas: no se interceptan.
    if (
        request.headers.get('X-Inertia') ||
        (url.origin === self.location.origin && isPrivatePath(url.pathname))
    ) {
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request).catch(() => caches.match(request)));
    }
});
