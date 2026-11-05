<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogo\MarcaRequest;
use App\Models\Marca;
use App\Support\ImagenUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MarcaController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['activas', 'inactivas'])],
        ]);

        $q = trim((string) ($filtros['q'] ?? ''));

        $marcas = Marca::query()
            ->withCount('productos')
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('nombre', 'like', '%'.$q.'%')
                ->orWhere('slug', 'like', '%'.$q.'%')))
            ->when(($filtros['estado'] ?? null) === 'activas', fn (Builder $query) => $query->where('activa', true))
            ->when(($filtros['estado'] ?? null) === 'inactivas', fn (Builder $query) => $query->where('activa', false))
            ->orderBy('nombre')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Marca $m) => [
                'id' => $m->id,
                'nombre' => $m->nombre,
                'slug' => $m->slug,
                'logo' => ImagenUrl::resolver($m->logo),
                'activa' => (bool) $m->activa,
                'productos_count' => (int) $m->productos_count,
            ]);

        return Inertia::render('Admin/Marcas/Index', [
            'marcas' => $marcas,
            'filtros' => ['q' => $q, 'estado' => $filtros['estado'] ?? ''],
        ]);
    }

    public function store(MarcaRequest $request): RedirectResponse
    {
        $marca = new Marca;
        $this->llenar($marca, $request->validated(), $request);
        $marca->save();

        return back()->with('success', 'Marca «'.$marca->nombre.'» creada.');
    }

    public function show(Marca $marca): RedirectResponse
    {
        return redirect()->route('admin.marcas.index', ['q' => $marca->nombre]);
    }

    public function update(MarcaRequest $request, Marca $marca): RedirectResponse
    {
        $this->llenar($marca, $request->validated(), $request);
        $marca->save();

        return back()->with('success', 'Marca «'.$marca->nombre.'» actualizada.');
    }

    public function estado(Marca $marca): RedirectResponse
    {
        $marca->forceFill(['activa' => ! $marca->activa])->save();

        return back()->with('success', 'Marca «'.$marca->nombre.'» '.($marca->activa ? 'activada' : 'desactivada').'.');
    }

    public function destroy(Marca $marca): RedirectResponse
    {
        $productos = $marca->productos()->count();

        if ($productos > 0) {
            return back()->with('error', "«{$marca->nombre}» tiene {$productos} producto(s) asociados. Desactívala en lugar de eliminarla.");
        }

        $this->borrarLogo($marca->logo);
        $marca->delete();

        return back()->with('success', 'Marca eliminada.');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function llenar(Marca $marca, array $datos, Request $request): void
    {
        $marca->forceFill([
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre'], $marca->id, $marca->slug),
            'activa' => (bool) ($datos['activa'] ?? true),
        ]);

        if (($datos['quitar_logo'] ?? false) && $marca->logo) {
            $this->borrarLogo($marca->logo);
            $marca->logo = null;
        }

        if ($request->hasFile('logo')) {
            $this->borrarLogo($marca->logo);
            $marca->logo = $request->file('logo')->store('marcas', 'public');
        }
    }

    private function slugUnico(?string $slug, string $nombre, ?int $ignorar, ?string $actual): string
    {
        if (! $slug && $actual) {
            return $actual;
        }

        $base = Str::limit(Str::slug($slug ?: $nombre) ?: 'marca', 160, '');
        $candidato = $base;
        $n = 2;

        while (Marca::query()->when($ignorar, fn (Builder $q) => $q->whereKeyNot($ignorar))->where('slug', $candidato)->exists()) {
            $candidato = $base.'-'.$n++;
        }

        return $candidato;
    }

    private function borrarLogo(?string $ruta): void
    {
        if ($ruta && ! Str::startsWith($ruta, ['http://', 'https://']) && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
