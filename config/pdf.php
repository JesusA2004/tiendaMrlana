<?php

/*
|--------------------------------------------------------------------------
| PDF (Browsershot + Chrome headless)
|--------------------------------------------------------------------------
| App\Services\Pdf\PdfService usa spatie/browsershot, que ejecuta Node + puppeteer.
| Si Node/Chrome no están disponibles se lanza PdfNoDisponibleException (mensaje
| controlado, nunca un 500).
|
| - node_binary / npm_binary: rutas absolutas si `node`/`npm` no están en el PATH del
|   usuario que ejecuta PHP (p. ej. "C:\Program Files\nodejs\node.exe" o "/usr/bin/node").
| - chrome_path: Chrome/Chromium a usar. Vacío = el que descargó puppeteer (npm install).
| - no_sandbox: true en servidores Linux que corren como root/contenedor.
| - timeout: segundos máximos para generar un documento.
*/

return [
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    'node_modules_path' => env('BROWSERSHOT_NODE_MODULES_PATH'),
    'no_sandbox' => (bool) env('BROWSERSHOT_NO_SANDBOX', false),
    'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),
    'formato' => env('PDF_FORMATO', 'Letter'),
];
