<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoPublicacion;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de publicación: borrador → pendiente_aprobacion → publicado ↔ pausado.
 * Publicar exige que `faltantesParaPublicar()` esté vacío.
 */
class ProductoPublicacionController extends Controller
{
    public function enviarAprobacion(Producto $producto): RedirectResponse
    {
        if (! in_array($producto->estado_publicacion, [EstadoPublicacion::Borrador->value, EstadoPublicacion::Pausado->value], true)) {
            return back()->with('error', 'Sólo los borradores o productos pausados pueden enviarse a aprobación.');
        }

        $producto->forceFill(['estado_publicacion' => EstadoPublicacion::PendienteAprobacion->value])->save();

        return back()->with('success', '«'.$producto->nombre.'» quedó en la cola de aprobación.');
    }

    public function publicar(Request $request, Producto $producto): RedirectResponse
    {
        return DB::transaction(function () use ($request, $producto) {
            /** @var Producto $bloqueado */
            $bloqueado = Producto::query()->whereKey($producto->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->estado_publicacion === EstadoPublicacion::Publicado->value && $bloqueado->estaPublicado()) {
                return back()->with('success', '«'.$bloqueado->nombre.'» ya está publicado.');
            }

            $faltantes = $bloqueado->faltantesParaPublicar();

            if ($faltantes !== []) {
                return back()
                    ->withErrors(['publicacion' => $faltantes])
                    ->with('error', 'Aún no se puede publicar: '.implode(' ', $faltantes));
            }

            $bloqueado->forceFill([
                'estado_publicacion' => EstadoPublicacion::Publicado->value,
                'activo' => true,
                'visible' => true,
                'aprobado_por' => $request->user()->id,
                'aprobado_en' => now(),
                'publicado_en' => $bloqueado->publicado_en ?? now(),
            ])->save();

            return back()->with('success', '«'.$bloqueado->nombre.'» ya está publicado en la tienda.');
        });
    }

    public function pausar(Producto $producto): RedirectResponse
    {
        if ($producto->estado_publicacion === EstadoPublicacion::Pausado->value) {
            return back()->with('success', '«'.$producto->nombre.'» ya estaba pausado.');
        }

        $producto->forceFill(['estado_publicacion' => EstadoPublicacion::Pausado->value])->save();

        return back()->with('success', '«'.$producto->nombre.'» se pausó y ya no aparece en la tienda.');
    }

    public function borrador(Producto $producto): RedirectResponse
    {
        if ($producto->estado_publicacion === EstadoPublicacion::Publicado->value) {
            return back()->with('error', 'Pausa el producto antes de regresarlo a borrador.');
        }

        $producto->forceFill(['estado_publicacion' => EstadoPublicacion::Borrador->value])->save();

        return back()->with('success', '«'.$producto->nombre.'» regresó a borrador.');
    }
}
