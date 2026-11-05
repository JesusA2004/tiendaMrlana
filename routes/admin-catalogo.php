<?php

/*
|--------------------------------------------------------------------------
| Admin · Catálogo (equipo Catálogo)
|--------------------------------------------------------------------------
| Productos, imágenes, inventario/movimientos, categorías, marcas, importación y aprobación.
| Todo protegido por el gate `gestionar-catalogo` (sólo admin).
*/

use App\Http\Controllers\Admin\CategoriaController as AdminCategoriaController;
use App\Http\Controllers\Admin\ImportacionCatalogoController;
use App\Http\Controllers\Admin\InventarioController as AdminInventarioController;
use App\Http\Controllers\Admin\MarcaController as AdminMarcaController;
use App\Http\Controllers\Admin\ProductoController as AdminProductoController;
use App\Http\Controllers\Admin\ProductoImagenController;
use App\Http\Controllers\Admin\ProductoPublicacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:gestionar-catalogo'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Productos
        Route::get('/productos', [AdminProductoController::class, 'index'])->name('productos.index');
        Route::get('/productos/crear', [AdminProductoController::class, 'create'])->name('productos.create');
        Route::post('/productos', [AdminProductoController::class, 'store'])->name('productos.store');
        Route::post('/productos/importar-catalogo', ImportacionCatalogoController::class)
            ->middleware('throttle:6,1')
            ->name('productos.importar');
        Route::get('/productos/{producto}', [AdminProductoController::class, 'show'])->name('productos.show');
        Route::get('/productos/{producto}/editar', [AdminProductoController::class, 'edit'])->name('productos.edit');
        Route::put('/productos/{producto}', [AdminProductoController::class, 'update'])->name('productos.update');
        Route::patch('/productos/{producto}/rapido', [AdminProductoController::class, 'rapido'])->name('productos.rapido');
        Route::delete('/productos/{producto}', [AdminProductoController::class, 'destroy'])->name('productos.destroy');

        // Publicación (borrador → pendiente_aprobacion → publicado ↔ pausado)
        Route::post('/productos/{producto}/enviar-aprobacion', [ProductoPublicacionController::class, 'enviarAprobacion'])->name('productos.publicacion.enviar');
        Route::post('/productos/{producto}/publicar', [ProductoPublicacionController::class, 'publicar'])->name('productos.publicacion.publicar');
        Route::post('/productos/{producto}/pausar', [ProductoPublicacionController::class, 'pausar'])->name('productos.publicacion.pausar');
        Route::post('/productos/{producto}/borrador', [ProductoPublicacionController::class, 'borrador'])->name('productos.publicacion.borrador');

        // Imágenes
        Route::post('/productos/{producto}/imagenes', [ProductoImagenController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('productos.imagenes.store');
        Route::put('/productos/{producto}/imagenes/orden', [ProductoImagenController::class, 'ordenar'])->name('productos.imagenes.ordenar');
        Route::patch('/productos/{producto}/imagenes/{imagen}/principal', [ProductoImagenController::class, 'principal'])->name('productos.imagenes.principal');
        Route::delete('/productos/{producto}/imagenes/{imagen}', [ProductoImagenController::class, 'destroy'])->name('productos.imagenes.destroy');

        // Inventario
        Route::get('/inventario', [AdminInventarioController::class, 'index'])->name('inventario.index');
        Route::post('/inventario/ajustes', [AdminInventarioController::class, 'store'])->name('inventario.ajustar');

        // Categorías
        Route::get('/categorias', [AdminCategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/categorias', [AdminCategoriaController::class, 'store'])->name('categorias.store');
        Route::get('/categorias/{categoria}', [AdminCategoriaController::class, 'show'])->name('categorias.show');
        Route::put('/categorias/{categoria}', [AdminCategoriaController::class, 'update'])->name('categorias.update');
        Route::patch('/categorias/{categoria}/estado', [AdminCategoriaController::class, 'estado'])->name('categorias.estado');
        Route::delete('/categorias/{categoria}', [AdminCategoriaController::class, 'destroy'])->name('categorias.destroy');

        // Marcas
        Route::get('/marcas', [AdminMarcaController::class, 'index'])->name('marcas.index');
        Route::post('/marcas', [AdminMarcaController::class, 'store'])->name('marcas.store');
        Route::get('/marcas/{marca}', [AdminMarcaController::class, 'show'])->name('marcas.show');
        Route::put('/marcas/{marca}', [AdminMarcaController::class, 'update'])->name('marcas.update');
        Route::patch('/marcas/{marca}/estado', [AdminMarcaController::class, 'estado'])->name('marcas.estado');
        Route::delete('/marcas/{marca}', [AdminMarcaController::class, 'destroy'])->name('marcas.destroy');
    });
