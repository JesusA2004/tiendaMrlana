<?php

/*
|--------------------------------------------------------------------------
| Tienda pública (equipo Tienda pública)
|--------------------------------------------------------------------------
| Home, catálogo, producto, páginas informativas, sitemap, robots.txt.
| Se conservan los nombres existentes: home, tienda.catalogo, tienda.show.
*/

use App\Http\Controllers\Publico\TiendaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TiendaController::class, 'index'])->name('home');
Route::get('/productos', [TiendaController::class, 'catalogo'])->name('tienda.catalogo');
Route::get('/productos/{producto:slug}', [TiendaController::class, 'show'])->name('tienda.show');

// Buscador (JSON ligero) y menú de categorías de la barra superior.
Route::get('/buscar/sugerencias', [TiendaController::class, 'sugerencias'])
    ->middleware('throttle:60,1')
    ->name('tienda.sugerencias');
Route::get('/buscar/categorias', [TiendaController::class, 'menu'])
    ->middleware('throttle:60,1')
    ->name('tienda.menu');

// Páginas informativas administrables (404 si no hay contenido configurado).
Route::get('/informacion/{slug}', [TiendaController::class, 'pagina'])
    ->whereIn('slug', array_keys(TiendaController::PAGINAS))
    ->name('tienda.pagina');
Route::get('/preguntas-frecuentes', [TiendaController::class, 'preguntas'])->name('tienda.preguntas');

Route::get('/sitemap.xml', [TiendaController::class, 'sitemap'])->name('tienda.sitemap');

// robots.txt dinámico (usa APP_URL; fuera de producción responde "Disallow: /"). No debe existir public/robots.txt.
Route::get('/robots.txt', [TiendaController::class, 'robots'])->name('tienda.robots');
