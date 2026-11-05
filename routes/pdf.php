<?php

/*
|--------------------------------------------------------------------------
| PDF de pedidos para staff (equipo Compra)
|--------------------------------------------------------------------------
| El PDF del cliente (cliente.pedidos.pdf) vive en routes/compra.php.
| Operación enlaza estas rutas desde el panel de pedidos.
*/

use App\Http\Controllers\Pdf\PedidoPdfController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:operar-pedidos'])
    ->prefix('admin/pedidos/{pedido}')
    ->name('admin.pedidos.')
    ->group(function () {
        Route::get('/pdf', [PedidoPdfController::class, 'admin'])->name('pdf');
        Route::get('/hoja-empaque', [PedidoPdfController::class, 'hojaEmpaque'])->name('hoja-empaque');
    });
