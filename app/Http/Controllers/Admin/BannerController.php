<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\ImagenUrl;
use App\Support\TextoPlano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Banners del carrusel de inicio (imagen en disco public, orden, vigencia y enlace opcional).
 */
class BannerController extends Controller
{
    public function index(): Response
    {
        $banners = Banner::query()
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'titulo' => $b->titulo,
                'descripcion' => $b->descripcion,
                'imagen' => ImagenUrl::resolver($b->imagen),
                'url' => $b->url,
                'activo' => (bool) $b->activo,
                'orden' => (int) $b->orden,
                'inicia_en' => $b->inicia_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'termina_en' => $b->termina_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'vigencia' => match (true) {
                    ! $b->activo => 'inactivo',
                    $b->termina_en !== null && $b->termina_en->isPast() => 'vencido',
                    $b->inicia_en !== null && $b->inicia_en->isFuture() => 'programado',
                    default => 'visible',
                },
            ]);

        return Inertia::render('Admin/Banners/Index', ['banners' => $banners]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request, true);

        $banner = new Banner($data);
        $banner->orden = ((int) Banner::max('orden')) + 1;
        $banner->imagen = $request->file('imagen_file')->store('banners', 'public');
        $banner->save();

        return back()->with('success', 'Banner creado.');
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validar($request, false);
        $banner->fill($data);

        if ($request->hasFile('imagen_file')) {
            $this->borrarImagen($banner);
            $banner->imagen = $request->file('imagen_file')->store('banners', 'public');
        }

        $banner->save();

        return back()->with('success', 'Banner actualizado.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        DB::transaction(function () use ($banner) {
            $banner->delete();

            Banner::query()->orderBy('orden')->orderBy('id')->pluck('id')
                ->each(fn (int $id, int $i) => Banner::whereKey($id)->update(['orden' => $i + 1]));
        });

        $this->borrarImagen($banner);

        return back()->with('success', 'Banner eliminado.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:banners,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['items']) as $i => $item) {
                Banner::whereKey($item['id'])->update(['orden' => $i + 1]);
            }
        });

        return back()->with('success', 'Orden de banners actualizado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $nuevo): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:180'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            // Enlace interno (/catalogo?...) o https externo.
            'url' => ['nullable', 'string', 'max:191', 'regex:/^(\/(?!\/)[^\s]*|https:\/\/[^\s]+)$/'],
            'imagen_file' => [$nuevo ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=300'],
            'activo' => ['boolean'],
            'inicia_en' => ['nullable', 'date'],
            'termina_en' => ['nullable', 'date', 'after:inicia_en'],
        ], [
            'url.regex' => 'Usa una ruta interna que empiece con / o un enlace https://',
            'imagen_file.required' => 'Selecciona la imagen del banner.',
            'imagen_file.dimensions' => 'La imagen debe medir al menos 800 × 300 px.',
            'termina_en.after' => 'La fecha de término debe ser posterior al inicio.',
        ]);

        $local = fn (?string $v) => $v ? Carbon::parse($v, 'America/Mexico_City')->setTimezone(config('app.timezone'))->toDateTimeString() : null;

        return [
            'titulo' => TextoPlano::linea($data['titulo']),
            'descripcion' => TextoPlano::limpiar($data['descripcion'] ?? null, false),
            'url' => $data['url'] ?? null,
            'activo' => $request->boolean('activo', true),
            'inicia_en' => $local($data['inicia_en'] ?? null),
            'termina_en' => $local($data['termina_en'] ?? null),
        ];
    }

    private function borrarImagen(Banner $banner): void
    {
        $ruta = $banner->getOriginal('imagen') ?? $banner->imagen;

        if ($ruta && ! str_starts_with($ruta, 'http') && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }
}
