<?php

/*
|--------------------------------------------------------------------------
| Compra (equipo Compra)
|--------------------------------------------------------------------------
| Carrito, cupón, checkout, gracias/instrucciones, comprobantes, mis pedidos, PDF, direcciones.
| Sin pagos con tarjeta: no existe ninguna ruta de cobro en línea (checkout.pagar eliminada).
| Las rutas PDF de staff (admin.pedidos.pdf / hoja-empaque) están en routes/pdf.php.
*/

use App\Http\Controllers\Cliente\ComprobanteController;
use App\Http\Controllers\Cliente\DireccionController;
use App\Http\Controllers\Cliente\MisPedidosController;
use App\Http\Controllers\Pdf\PedidoPdfController;
use App\Http\Controllers\Publico\CarritoController;
use App\Http\Controllers\Publico\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/carrito', [CarritoController::class, 'index'])->name('carrito.index');

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/carrito/agregar', [CarritoController::class, 'agregar'])->name('carrito.agregar');

    Route::post('/carrito/cupon', [CarritoController::class, 'aplicarCupon'])
        ->middleware('throttle:15,1')
        ->name('carrito.cupon.aplicar');
    Route::delete('/carrito/cupon', [CarritoController::class, 'quitarCupon'])->name('carrito.cupon.quitar');

    Route::patch('/carrito/{producto}', [CarritoController::class, 'actualizar'])->name('carrito.actualizar');
    Route::delete('/carrito/{producto}', [CarritoController::class, 'eliminar'])->name('carrito.eliminar');
    Route::delete('/carrito', [CarritoController::class, 'vaciar'])->name('carrito.vaciar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('checkout.store');
    Route::get('/checkout/gracias/{pedido}', [CheckoutController::class, 'gracias'])->name('checkout.gracias');

    Route::middleware('role:cliente,admin,vendedor')->prefix('mi-cuenta')->name('cliente.')->group(function () {
        Route::get('/pedidos', [MisPedidosController::class, 'index'])->name('pedidos.index');
        Route::get('/pedidos/{pedido}', [MisPedidosController::class, 'show'])->name('pedidos.show');
        Route::get('/pedidos/{pedido}/pdf', [PedidoPdfController::class, 'cliente'])->name('pedidos.pdf');

        Route::post('/pedidos/{pedido}/comprobantes', [ComprobanteController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('pedidos.comprobantes.store');
        Route::post('/pedidos/{pedido}/envio', [MisPedidosController::class, 'elegirEnvio'])
            ->middleware('throttle:20,1')
            ->name('pedidos.envio.elegir');
        Route::get('/pedidos/{pedido}/comprobantes/{comprobante}', [ComprobanteController::class, 'descargar'])
            ->name('pedidos.comprobantes.descargar');

        Route::get('/direcciones', [DireccionController::class, 'index'])->name('direcciones.index');
        Route::post('/direcciones', [DireccionController::class, 'store'])->name('direcciones.store');
        Route::put('/direcciones/{direccion}', [DireccionController::class, 'update'])->name('direcciones.update');
        Route::delete('/direcciones/{direccion}', [DireccionController::class, 'destroy'])->name('direcciones.destroy');
    });
});
