<?php

namespace App\Http\Controllers\Publico;

use App\Exceptions\DominioException;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Services\Carrito\CarritoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carrito en sesión: sólo producto_id + cantidad. Todos los importes los calcula CarritoService.
 */
class CarritoController extends Controller
{
    public function __construct(private readonly CarritoService $carrito) {}

    public function index(Request $request): Response
    {
        $calculo = $this->carrito->resumen($request->user());

        return Inertia::render('Tienda/Carrito', [
            'items' => $calculo['items'],
            'cupon' => $calculo['cupon'],
            'resumen' => $calculo['resumen'],
            'envio' => $calculo['envio'],
            'errores' => $calculo['errores'],
            'puede_comprar' => $calculo['puede_comprar'],
            'cupon_error' => $calculo['cupon_error'],
            'autenticado' => $request->user() !== null,
        ]);
    }

    public function agregar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:'.CarritoService::MAX_POR_LINEA],
        ]);

        try {
            $producto = $this->carrito->agregar((int) $data['producto_id'], (int) ($data['cantidad'] ?? 1));
        } catch (DominioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $producto->nombre.' se agregó al carrito.');
    }

    public function actualizar(Request $request, Producto $producto): RedirectResponse
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:0', 'max:'.CarritoService::MAX_POR_LINEA],
        ]);

        try {
            $this->carrito->actualizar($producto->id, (int) $data['cantidad']);
        } catch (DominioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Se actualizó la cantidad de '.$producto->nombre.'.');
    }

    public function eliminar(Producto $producto): RedirectResponse
    {
        $this->carrito->eliminar($producto->id);

        return back()->with('success', $producto->nombre.' se quitó del carrito.');
    }

    public function vaciar(): RedirectResponse
    {
        $this->carrito->vaciar();

        return back()->with('success', 'Tu carrito se vació correctamente.');
    }

    public function aplicarCupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:60'],
        ], [
            'codigo.required' => 'Debes ingresar un código de cupón.',
        ]);

        try {
            $this->carrito->aplicarCupon($data['codigo'], $request->user());
        } catch (DominioException $e) {
            // Error junto al campo del cupón (mensaje claro del CuponService).
            return back()->withErrors(['codigo' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Cupón aplicado correctamente.');
    }

    public function quitarCupon(): RedirectResponse
    {
        $this->carrito->quitarCupon();

        return back()->with('success', 'Cupón eliminado del carrito.');
    }
}
