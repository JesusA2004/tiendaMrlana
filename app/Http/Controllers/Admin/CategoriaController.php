<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalogo\CategoriaRequest;
use App\Models\Categoria;
use App\Support\ImagenUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['activas', 'inactivas'])],
        ]);

        $q = trim((string) ($filtros['q'] ?? ''));

        $categorias = Categoria::query()
            ->withCount(['productos', 'hijas'])
            ->when($q !== '', fn (Builder $query) => $query->where(fn (Builder $w) => $w
                ->where('nombre', 'like', '%'.$q.'%')
                ->orWhere('slug', 'like', '%'.$q.'%')))
            ->when(($filtros['estado'] ?? null) === 'activas', fn (Builder $query) => $query->where('activa', true))
            ->when(($filtros['estado'] ?? null) === 'inactivas', fn (Builder $query) => $query->where('activa', false))
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $todas = Categoria::query()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id']);

        return Inertia::render('Admin/Categorias/Index', [
            'categorias' => $this->ordenarComoArbol($categorias)->map(fn (array $fila) => [
                'id' => $fila['categoria']->id,
                'nombre' => $fila['categoria']->nombre,
                'slug' => $fila['categoria']->slug,
                'descripcion' => $fila['categoria']->descripcion,
                'categoria_padre_id' => $fila['categoria']->categoria_padre_id,
                'padre' => $todas->firstWhere('id', $fila['categoria']->categoria_padre_id)?->nombre,
                'nivel' => $fila['nivel'],
                'imagen' => ImagenUrl::resolver($fila['categoria']->imagen),
                'activa' => (bool) $fila['categoria']->activa,
                'orden' => (int) $fila['categoria']->orden,
                'productos_count' => (int) $fila['categoria']->productos_count,
                'hijas_count' => (int) $fila['categoria']->hijas_count,
            ])->values(),
            'padres' => $todas->map(fn (Categoria $c) => ['id' => $c->id, 'nombre' => $c->nombre, 'categoria_padre_id' => $c->categoria_padre_id]),
            'filtros' => ['q' => $q, 'estado' => $filtros['estado'] ?? ''],
        ]);
    }

    public function store(CategoriaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $categoria = new Categoria;
        $this->llenar($categoria, $datos, $request);
        $categoria->save();

        return back()->with('success', 'Categoría «'.$categoria->nombre.'» creada.');
    }

    public function show(Categoria $categoria): RedirectResponse
    {
        return redirect()->route('admin.categorias.index', ['q' => $categoria->nombre]);
    }

    public function update(CategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $this->llenar($categoria, $request->validated(), $request);
        $categoria->save();

        return back()->with('success', 'Categoría «'.$categoria->nombre.'» actualizada.');
    }

    public function estado(Categoria $categoria): RedirectResponse
    {
        $categoria->forceFill(['activa' => ! $categoria->activa])->save();

        return back()->with('success', 'Categoría «'.$categoria->nombre.'» '.($categoria->activa ? 'activada' : 'desactivada').'.');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        $productos = $categoria->productos()->count();

        if ($productos > 0) {
            return back()->with('error', "«{$categoria->nombre}» tiene {$productos} producto(s) asociados. Desactívala en lugar de eliminarla o reasigna sus productos.");
        }

        if ($categoria->hijas()->exists()) {
            return back()->with('error', "«{$categoria->nombre}» tiene subcategorías. Muévelas o elimínalas primero.");
        }

        $this->borrarImagen($categoria->imagen);
        $categoria->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function llenar(Categoria $categoria, array $datos, Request $request): void
    {
        $categoria->forceFill([
            'nombre' => $datos['nombre'],
            'slug' => $this->slugUnico($datos['slug'] ?? null, $datos['nombre'], $categoria->id, $categoria->slug),
            'categoria_padre_id' => $datos['categoria_padre_id'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'activa' => (bool) ($datos['activa'] ?? true),
            'orden' => (int) ($datos['orden'] ?? 0),
        ]);

        if (($datos['quitar_imagen'] ?? false) && $categoria->imagen) {
            $this->borrarImagen($categoria->imagen);
            $categoria->imagen = null;
        }

        if ($request->hasFile('imagen')) {
            $this->borrarImagen($categoria->imagen);
            $categoria->imagen = $request->file('imagen')->store('categorias', 'public');
        }
    }

    /**
     * @param  Collection<int, Categoria>  $categorias
     * @return Collection<int, array{categoria: Categoria, nivel: int}>
     */
    private function ordenarComoArbol(Collection $categorias): Collection
    {
        $ids = $categorias->pluck('id')->all();
        $porPadre = $categorias->groupBy(fn (Categoria $c) => in_array($c->categoria_padre_id, $ids, true) ? $c->categoria_padre_id : 0);
        $resultado = collect();

        $agregar = function (int $padre, int $nivel) use (&$agregar, $porPadre, $resultado) {
            foreach ($porPadre->get($padre, collect()) as $categoria) {
                $resultado->push(['categoria' => $categoria, 'nivel' => $nivel]);

                if ($nivel < 5) {
                    $agregar($categoria->id, $nivel + 1);
                }
            }
        };

        $agregar(0, 0);

        return $resultado;
    }

    private function slugUnico(?string $slug, string $nombre, ?int $ignorar, ?string $actual): string
    {
        if (! $slug && $actual) {
            return $actual;
        }

        $base = Str::limit(Str::slug($slug ?: $nombre) ?: 'categoria', 160, '');
        $candidato = $base;
        $n = 2;

        while (Categoria::query()->when($ignorar, fn (Builder $q) => $q->whereKeyNot($ignorar))->where('slug', $candidato)->exists()) {
            $candidato = $base.'-'.$n++;
        }

        return $candidato;
    }

    private function borrarImagen(?string $ruta): void
    {
        if ($ruta && ! Str::startsWith($ruta, ['http://', 'https://']) && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
