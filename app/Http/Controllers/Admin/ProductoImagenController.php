<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogo\ProductoImagenesRequest;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Services\Catalogo\ImagenesProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Galería de imágenes del producto (subir, ordenar, principal, eliminar).
 */
class ProductoImagenController extends Controller
{
    public function __construct(private readonly ImagenesProducto $imagenes) {}

    public function store(ProductoImagenesRequest $request, Producto $producto): RedirectResponse
    {
        $archivos = array_values((array) $request->file('imagenes', []));
        $creadas = $this->imagenes->agregar($producto, $archivos);

        $n = count($creadas);

        return back()->with('success', $n === 1 ? 'Imagen agregada.' : "{$n} imágenes agregadas.");
    }

    public function ordenar(Request $request, Producto $producto): RedirectResponse
    {
        $datos = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $this->imagenes->ordenar($producto, array_map('intval', $datos['ids']));

        return back()->with('success', 'Orden de imágenes actualizado.');
    }

    public function principal(Producto $producto, ProductoImagen $imagen): RedirectResponse
    {
        $this->asegurarPertenece($producto, $imagen);
        $this->imagenes->marcarPrincipal($producto, $imagen);

        return back()->with('success', 'Imagen principal actualizada.');
    }

    public function destroy(Producto $producto, ProductoImagen $imagen): RedirectResponse
    {
        $this->asegurarPertenece($producto, $imagen);

        if ($producto->estaPublicado() && $producto->imagenes()->count() <= 1) {
            return back()->with('error', 'Es la única imagen de un producto publicado: sube otra o pausa el producto antes de eliminarla.');
        }

        $this->imagenes->eliminar($producto, $imagen);

        return back()->with('success', 'Imagen eliminada.');
    }

    private function asegurarPertenece(Producto $producto, ProductoImagen $imagen): void
    {
        abort_unless((int) $imagen->producto_id === (int) $producto->id, 404);
    }
}
