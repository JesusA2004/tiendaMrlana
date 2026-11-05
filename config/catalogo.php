<?php

return [
    /*
    | CatalogoMueblesSeeder: con true adjunta las fotos del catálogo y publica los muebles con el precio
    | del catálogo (equivale a `php artisan catalogo:importar-muebles --imagenes --publicar`).
    | Requiere `php artisan storage:link`. Idempotente: no duplica fotos ni pisa precios del admin.
    */
    'muebles_publicar' => (bool) env('CATALOGO_MUEBLES_PUBLICAR', false),
];
