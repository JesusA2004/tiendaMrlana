<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paqueteria;
use App\Support\TextoPlano;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de paqueterías para cotizar envíos (sin datos precargados: las da de alta el admin).
 * La URL de rastreo es opcional y debe llevar el marcador {guia}.
 */
class PaqueteriaController extends Controller
{
    public function index(): Response
    {
        $paqueterias = Paqueteria::query()
            ->withCount('opciones')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Paqueteria $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'activo' => (bool) $p->activo,
                'url_rastreo' => $p->url_rastreo,
                'notas' => $p->notas,
                'opciones_count' => (int) $p->opciones_count,
                'ejemplo_rastreo' => $p->urlRastreo('1234567890'),
            ]);

        return Inertia::render('Admin/Paqueterias/Index', [
            'paqueterias' => $paqueterias,
            'marcador' => Paqueteria::MARCADOR_GUIA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Paqueteria::create($this->validar($request));

        return back()->with('success', 'Paquetería agregada.');
    }

    public function update(Request $request, Paqueteria $paqueteria): RedirectResponse
    {
        $paqueteria->update($this->validar($request, $paqueteria));

        return back()->with('success', 'Paquetería actualizada.');
    }

    public function destroy(Paqueteria $paqueteria): RedirectResponse
    {
        // Si ya se cotizó con ella, se desactiva para conservar el rastreo de pedidos pasados.
        if ($paqueteria->opciones()->exists()) {
            $paqueteria->update(['activo' => false]);

            return back()->with('success', 'La paquetería ya se usó en cotizaciones; se desactivó en lugar de eliminarse.');
        }

        $paqueteria->delete();

        return back()->with('success', 'Paquetería eliminada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Paqueteria $paqueteria = null): array
    {
        $request->merge([
            'nombre' => TextoPlano::linea($request->input('nombre')),
            'url_rastreo' => filled($request->input('url_rastreo')) ? trim((string) $request->input('url_rastreo')) : null,
        ]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:80', Rule::unique('paqueterias', 'nombre')->ignore($paqueteria?->id)],
            'activo' => ['nullable', 'boolean'],
            'url_rastreo' => ['nullable', 'string', 'max:500', function (string $attr, mixed $valor, Closure $fail) {
                if (! str_contains((string) $valor, Paqueteria::MARCADOR_GUIA)) {
                    $fail('La URL de rastreo debe incluir el marcador '.Paqueteria::MARCADOR_GUIA.' donde va el número de guía.');

                    return;
                }

                $prueba = str_replace(Paqueteria::MARCADOR_GUIA, 'GUIA123', (string) $valor);

                if (! preg_match('#^https?://#i', $prueba) || filter_var($prueba, FILTER_VALIDATE_URL) === false) {
                    $fail('Escribe una URL válida que empiece con https:// o http://.');
                }
            }],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'nombre.required' => 'Escribe el nombre de la paquetería.',
            'nombre.unique' => 'Ya existe una paquetería con ese nombre.',
        ]);

        return [
            'nombre' => $data['nombre'],
            'activo' => $request->boolean('activo', true),
            'url_rastreo' => $data['url_rastreo'] ?? null,
            'notas' => TextoPlano::limpiar($data['notas'] ?? null),
        ];
    }
}
