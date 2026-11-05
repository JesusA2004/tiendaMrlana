<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Oferta;
use App\Models\Producto;
use App\Support\TextoPlano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ofertas por producto, categoría o marca (las aplica PrecioService).
 */
class OfertaController extends Controller
{
    public const TIPOS = [
        ['value' => 'porcentaje', 'label' => 'Porcentaje'],
        ['value' => 'monto_fijo', 'label' => 'Monto fijo'],
    ];

    public const APLICA = [
        ['value' => 'productos', 'label' => 'Productos seleccionados'],
        ['value' => 'categoria', 'label' => 'Categoría'],
        ['value' => 'marca', 'label' => 'Marca'],
    ];

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');

        $ofertas = Oferta::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'productos:id,nombre'])
            ->when($buscar !== '', fn (Builder $q) => $q->where('nombre', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%'))
            ->when($estado === 'vigentes', fn (Builder $q) => $q->activas())
            ->when($estado === 'inactivas', fn (Builder $q) => $q->where('activa', false))
            ->when($estado === 'vencidas', fn (Builder $q) => $q->whereNotNull('termina_en')->where('termina_en', '<', now()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Oferta $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'tipo' => $o->tipo,
                'valor' => (float) $o->valor,
                'aplica_a' => $o->aplica_a,
                'categoria_id' => $o->categoria_id,
                'categoria_nombre' => $o->categoria?->nombre,
                'marca_id' => $o->marca_id,
                'marca_nombre' => $o->marca?->nombre,
                'productos_ids' => $o->productos->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'productos_nombres' => $o->productos->pluck('nombre')->values(),
                'inicia_en' => $o->inicia_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'termina_en' => $o->termina_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'activa' => (bool) $o->activa,
                'vigencia' => match (true) {
                    ! $o->activa => 'inactiva',
                    $o->termina_en !== null && $o->termina_en->isPast() => 'vencida',
                    $o->inicia_en !== null && $o->inicia_en->isFuture() => 'programada',
                    default => 'vigente',
                },
            ]);

        return Inertia::render('Admin/Ofertas/Index', [
            'ofertas' => $ofertas,
            'tipos' => self::TIPOS,
            'aplicaOptions' => self::APLICA,
            'categorias' => Categoria::query()->orderBy('nombre')->get(['id', 'nombre']),
            'marcas' => Marca::query()->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::query()->orderBy('nombre')->get(['id', 'nombre', 'estado_publicacion']),
            'filtros' => ['buscar' => $buscar, 'estado' => $estado],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$datos, $productos] = $this->validar($request);

        DB::transaction(function () use ($datos, $productos) {
            Oferta::create($datos)->productos()->sync($productos);
        });

        return back()->with('success', 'Oferta creada.');
    }

    public function update(Request $request, Oferta $oferta): RedirectResponse
    {
        [$datos, $productos] = $this->validar($request);

        DB::transaction(function () use ($oferta, $datos, $productos) {
            $oferta->update($datos);
            $oferta->productos()->sync($productos);
        });

        return back()->with('success', 'Oferta actualizada.');
    }

    public function destroy(Oferta $oferta): RedirectResponse
    {
        DB::transaction(function () use ($oferta) {
            $oferta->productos()->detach();
            $oferta->delete();
        });

        return back()->with('success', 'Oferta eliminada.');
    }

    /** @return array{0: array<string, mixed>, 1: list<int>} */
    private function validar(Request $request): array
    {
        $porcentaje = $request->input('tipo') === 'porcentaje';
        $aplica = (string) $request->input('aplica_a');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(array_column(self::TIPOS, 'value'))],
            'valor' => $porcentaje
                ? ['required', 'numeric', 'min:1', 'max:100']
                : ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'aplica_a' => ['required', Rule::in(array_column(self::APLICA, 'value'))],
            'categoria_id' => [Rule::requiredIf($aplica === 'categoria'), 'nullable', 'integer', 'exists:categorias,id'],
            'marca_id' => [Rule::requiredIf($aplica === 'marca'), 'nullable', 'integer', 'exists:marcas,id'],
            'productos_ids' => [Rule::requiredIf($aplica === 'productos'), 'nullable', 'array', $aplica === 'productos' ? 'min:1' : 'min:0', 'max:500'],
            'productos_ids.*' => ['integer', 'distinct', 'exists:productos,id'],
            'inicia_en' => ['nullable', 'date'],
            'termina_en' => ['nullable', 'date', 'after:inicia_en'],
            'activa' => ['boolean'],
        ], [
            'valor.min' => $porcentaje ? 'El porcentaje debe ser de 1 a 100.' : 'El monto debe ser mayor a cero.',
            'valor.max' => $porcentaje ? 'El porcentaje debe ser de 1 a 100.' : 'El monto es demasiado alto.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'marca_id.required' => 'Selecciona una marca.',
            'productos_ids.required' => 'Selecciona al menos un producto.',
            'productos_ids.min' => 'Selecciona al menos un producto.',
            'termina_en.after' => 'La fecha de término debe ser posterior al inicio.',
        ]);

        $local = fn (?string $v) => $v ? Carbon::parse($v, 'America/Mexico_City')->setTimezone(config('app.timezone'))->toDateTimeString() : null;

        return [[
            'nombre' => TextoPlano::linea($data['nombre']),
            'tipo' => $data['tipo'],
            'valor' => round((float) $data['valor'], 2),
            'aplica_a' => $aplica,
            'categoria_id' => $aplica === 'categoria' ? (int) $data['categoria_id'] : null,
            'marca_id' => $aplica === 'marca' ? (int) $data['marca_id'] : null,
            'inicia_en' => $local($data['inicia_en'] ?? null),
            'termina_en' => $local($data['termina_en'] ?? null),
            'activa' => $request->boolean('activa', true),
        ], $aplica === 'productos' ? array_map('intval', $data['productos_ids'] ?? []) : []];
    }
}
