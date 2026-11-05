<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use Illuminate\Http\RedirectResponse;

/**
 * Reimporta (idempotente) el catálogo de muebles 2026 desde el panel.
 * Equivale a `php artisan catalogo:importar-muebles`.
 */
class ImportacionCatalogoController extends Controller
{
    public function __invoke(ImportadorCatalogoMuebles $importador): RedirectResponse
    {
        $resultado = $importador->importar();

        return redirect()
            ->route('admin.productos.index', ['estado' => 'pendiente_aprobacion'])
            ->with('success', 'Catálogo de muebles: '.$resultado->resumen());
    }
}
