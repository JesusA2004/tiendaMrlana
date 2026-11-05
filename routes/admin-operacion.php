<?php

/*
|--------------------------------------------------------------------------
| Admin · Operación (equipo Operación)
|--------------------------------------------------------------------------
| Dashboard, pedidos (workflow), pagos, comprobantes, envíos/reglas, configuración,
| métodos de pago, usuarios, banners, ofertas, cupones.
| Permisos finos por gate (docs/ARQUITECTURA.md §1). El vendedor usa el mismo panel
| /admin/pedidos con acciones limitadas por gate; las rutas vendedor.* sólo redirigen.
| Los gates también se verifican dentro de PedidoWorkflow (defensa en profundidad).
*/

use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\ComprobanteController as AdminComprobanteController;
use App\Http\Controllers\Admin\ConfiguracionController as AdminConfiguracionController;
use App\Http\Controllers\Admin\CuponController as AdminCuponController;
use App\Http\Controllers\Admin\EnvioController as AdminEnvioController;
use App\Http\Controllers\Admin\MetodoPagoController as AdminMetodoPagoController;
use App\Http\Controllers\Admin\OfertaController as AdminOfertaController;
use App\Http\Controllers\Admin\PagoController as AdminPagoController;
use App\Http\Controllers\Admin\PaqueteriaController as AdminPaqueteriaController;
use App\Http\Controllers\Admin\PedidoController as AdminPedidoController;
use App\Http\Controllers\Admin\ReglaEnvioController as AdminReglaEnvioController;
use App\Http\Controllers\Admin\UsuarioController as AdminUsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Vendedor\PedidoController as VendedorPedidoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin y vendedor ven su tablero; el cliente se redirige a «Mis pedidos».
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Rutas viejas del vendedor: ahora todo vive en /admin/pedidos.
    Route::prefix('vendedor')
        ->name('vendedor.')
        ->middleware('role:vendedor,admin')
        ->group(function () {
            Route::get('/pedidos', [VendedorPedidoController::class, 'index'])->name('pedidos.index');
            Route::get('/pedidos/{pedido}', [VendedorPedidoController::class, 'show'])->name('pedidos.show');
        });

    // ---------------------------------------------------------------- operación (admin + vendedor)
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin,vendedor')
        ->group(function () {
            Route::middleware('can:operar-pedidos')->group(function () {
                Route::get('/pedidos', [AdminPedidoController::class, 'index'])->name('pedidos.index');
                Route::get('/pedidos/{pedido}', [AdminPedidoController::class, 'show'])->name('pedidos.show');

                Route::post('/pedidos/{pedido}/preparacion', [AdminPedidoController::class, 'iniciarPreparacion'])->name('pedidos.preparacion');
                Route::post('/pedidos/{pedido}/listo-envio', [AdminPedidoController::class, 'listoEnvio'])->name('pedidos.listo-envio');
                Route::post('/pedidos/{pedido}/listo-recoger', [AdminPedidoController::class, 'listoRecoger'])->name('pedidos.listo-recoger');
                Route::post('/pedidos/{pedido}/envio', [AdminPedidoController::class, 'registrarEnvio'])->name('pedidos.envio');
                Route::post('/pedidos/{pedido}/entrega', [AdminPedidoController::class, 'confirmarEntrega'])->name('pedidos.entrega');
                Route::post('/pedidos/{pedido}/incidencia', [AdminPedidoController::class, 'incidencia'])->name('pedidos.incidencia');
                Route::post('/pedidos/{pedido}/notas', [AdminPedidoController::class, 'nota'])->name('pedidos.notas');

                Route::get('/envios', [AdminEnvioController::class, 'index'])->name('envios.index');
                Route::post('/envios/lote', [AdminEnvioController::class, 'registrarLote'])->name('envios.lote');

                // Visor/descarga de comprobantes privados (el controlador exige revisar-pagos u operar-pedidos).
                Route::get('/comprobantes/{comprobante}', [AdminComprobanteController::class, 'show'])->name('comprobantes.show');
                Route::get('/comprobantes/{comprobante}/descargar', [AdminComprobanteController::class, 'descargar'])->name('comprobantes.descargar');
            });

            Route::post('/pedidos/{pedido}/cotizar-envio', [AdminPedidoController::class, 'cotizarEnvio'])
                ->middleware('can:cotizar-envio')
                ->name('pedidos.cotizar-envio');
            Route::post('/pedidos/{pedido}/opciones-envio', [AdminPedidoController::class, 'opcionesEnvio'])
                ->middleware('can:cotizar-envio')
                ->name('pedidos.opciones-envio');

            Route::post('/pedidos/{pedido}/cancelar', [AdminPedidoController::class, 'cancelar'])
                ->middleware('can:cancelar-pedidos')
                ->name('pedidos.cancelar');

            Route::middleware('can:revisar-pagos')->group(function () {
                Route::post('/pedidos/{pedido}/reembolso', [AdminPedidoController::class, 'reembolso'])->name('pedidos.reembolso');

                Route::get('/pagos', [AdminPagoController::class, 'index'])->name('pagos.index');
                Route::post('/pagos/{pago}/aprobar', [AdminPagoController::class, 'aprobar'])->name('pagos.aprobar');
                Route::post('/pagos/{pago}/rechazar', [AdminPagoController::class, 'rechazar'])->name('pagos.rechazar');
                Route::post('/pagos/{pago}/confirmar-efectivo', [AdminPagoController::class, 'confirmarEfectivo'])->name('pagos.confirmar-efectivo');
            });
        });

    // ---------------------------------------------------------------- administración (sólo admin)
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::middleware('can:gestionar-configuracion')->group(function () {
                Route::get('/configuraciones', [AdminConfiguracionController::class, 'index'])->name('configuraciones.index');
                Route::put('/configuraciones/{seccion}', [AdminConfiguracionController::class, 'update'])
                    ->whereIn('seccion', AdminConfiguracionController::SECCIONES)
                    ->name('configuraciones.update');

                Route::get('/metodos-pago', [AdminMetodoPagoController::class, 'index'])->name('metodos-pago.index');
                Route::put('/metodos-pago/{metodoPago}', [AdminMetodoPagoController::class, 'update'])->name('metodos-pago.update');

                Route::get('/reglas-envio', [AdminReglaEnvioController::class, 'index'])->name('reglas-envio.index');
                Route::post('/reglas-envio', [AdminReglaEnvioController::class, 'store'])->name('reglas-envio.store');
                Route::put('/reglas-envio/{reglaEnvio}', [AdminReglaEnvioController::class, 'update'])->name('reglas-envio.update');
                Route::delete('/reglas-envio/{reglaEnvio}', [AdminReglaEnvioController::class, 'destroy'])->name('reglas-envio.destroy');

                Route::get('/paqueterias', [AdminPaqueteriaController::class, 'index'])->name('paqueterias.index');
                Route::post('/paqueterias', [AdminPaqueteriaController::class, 'store'])->name('paqueterias.store');
                Route::put('/paqueterias/{paqueteria}', [AdminPaqueteriaController::class, 'update'])->name('paqueterias.update');
                Route::delete('/paqueterias/{paqueteria}', [AdminPaqueteriaController::class, 'destroy'])->name('paqueterias.destroy');
            });

            Route::middleware('can:gestionar-usuarios')->group(function () {
                Route::get('/usuarios', [AdminUsuarioController::class, 'index'])->name('usuarios.index');
                Route::post('/usuarios', [AdminUsuarioController::class, 'store'])->name('usuarios.store');
                Route::put('/usuarios/{user}', [AdminUsuarioController::class, 'update'])->name('usuarios.update');
                Route::post('/usuarios/{user}/enlace-acceso', [AdminUsuarioController::class, 'enviarEnlace'])->name('usuarios.enlace');
                Route::delete('/usuarios/{user}', [AdminUsuarioController::class, 'destroy'])->name('usuarios.destroy');
            });

            // Promociones y contenido de la tienda.
            Route::middleware('can:gestionar-catalogo')->group(function () {
                Route::get('/ofertas', [AdminOfertaController::class, 'index'])->name('ofertas.index');
                Route::post('/ofertas', [AdminOfertaController::class, 'store'])->name('ofertas.store');
                Route::put('/ofertas/{oferta}', [AdminOfertaController::class, 'update'])->name('ofertas.update');
                Route::delete('/ofertas/{oferta}', [AdminOfertaController::class, 'destroy'])->name('ofertas.destroy');

                Route::get('/cupones', [AdminCuponController::class, 'index'])->name('cupones.index');
                Route::post('/cupones', [AdminCuponController::class, 'store'])->name('cupones.store');
                Route::put('/cupones/{cupon}', [AdminCuponController::class, 'update'])->name('cupones.update');
                Route::delete('/cupones/{cupon}', [AdminCuponController::class, 'destroy'])->name('cupones.destroy');

                Route::get('/banners', [AdminBannerController::class, 'index'])->name('banners.index');
                Route::post('/banners', [AdminBannerController::class, 'store'])->name('banners.store');
                Route::post('/banners/reorder', [AdminBannerController::class, 'reorder'])->name('banners.reorder');
                Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->name('banners.update');
                Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->name('banners.destroy');
            });
        });
});
