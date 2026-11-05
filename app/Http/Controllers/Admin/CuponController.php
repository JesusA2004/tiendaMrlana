<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use App\Support\TextoPlano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cupones (validados al aplicar por CuponService). Códigos únicos en mayúsculas.
 */
class CuponController extends Controller
{
    public const TIPOS = [
        ['value' => 'porcentaje', 'label' => 'Porcentaje'],
        ['value' => 'monto_fijo', 'label' => 'Monto fijo'],
    ];

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', '');

        $cupones = Cupon::query()
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $termino = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';
                $q->where(fn (Builder $s) => $s->where('codigo', 'like', $termino)->orWhere('nombre', 'like', $termino));
            })
            ->when($estado === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when($estado === 'inactivos', fn (Builder $q) => $q->where('activo', false))
            ->when($estado === 'vencidos', fn (Builder $q) => $q->whereNotNull('termina_en')->where('termina_en', '<', now()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Cupon $c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'tipo' => $c->tipo,
                'valor' => (float) $c->valor,
                'compra_minima' => $c->compra_minima !== null ? (float) $c->compra_minima : null,
                'usos_maximos' => $c->usos_maximos,
                'usos_por_usuario' => $c->usos_por_usuario,
                'usos_actuales' => (int) $c->usos_actuales,
                'inicia_en' => $c->inicia_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'termina_en' => $c->termina_en?->timezone('America/Mexico_City')->format('Y-m-d\TH:i'),
                'activo' => (bool) $c->activo,
                'vigencia' => $this->vigencia($c),
            ]);

        return Inertia::render('Admin/Cupones/Index', [
            'cupones' => $cupones,
            'tipos' => self::TIPOS,
            'filtros' => ['buscar' => $buscar, 'estado' => $estado],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Cupon::create($this->validar($request));

        return back()->with('success', 'Cupón creado.');
    }

    public function update(Request $request, Cupon $cupon): RedirectResponse
    {
        $cupon->update($this->validar($request, $cupon));

        return back()->with('success', 'Cupón actualizado.');
    }

    public function destroy(Cupon $cupon): RedirectResponse
    {
        if ($cupon->usos()->exists() || $cupon->usos_actuales > 0) {
            $cupon->update(['activo' => false]);

            return back()->with('success', 'El cupón ya se usó en pedidos; se desactivó para conservar el historial.');
        }

        $cupon->delete();

        return back()->with('success', 'Cupón eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Cupon $cupon = null): array
    {
        $request->merge(['codigo' => mb_strtoupper(preg_replace('/\s+/', '', (string) $request->input('codigo')))]);
        $porcentaje = $request->input('tipo') === 'porcentaje';

        $data = $request->validate([
            'codigo' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('cupones', 'codigo')->ignore($cupon?->id)],
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(array_column(self::TIPOS, 'value'))],
            'valor' => $porcentaje
                ? ['required', 'numeric', 'min:1', 'max:100']
                : ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'compra_minima' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'usos_maximos' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'usos_por_usuario' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'inicia_en' => ['nullable', 'date'],
            'termina_en' => ['nullable', 'date', 'after:inicia_en'],
            'activo' => ['boolean'],
        ], [
            'codigo.regex' => 'El código sólo admite letras, números, guion y guion bajo.',
            'codigo.unique' => 'Ya existe un cupón con ese código.',
            'valor.min' => $porcentaje ? 'El porcentaje debe ser de 1 a 100.' : 'El monto debe ser mayor a cero.',
            'valor.max' => $porcentaje ? 'El porcentaje debe ser de 1 a 100.' : 'El monto es demasiado alto.',
            'termina_en.after' => 'La fecha de término debe ser posterior al inicio.',
        ]);

        if ($cupon && isset($data['usos_maximos']) && $data['usos_maximos'] < $cupon->usos_actuales) {
            $request->validate(['usos_maximos' => ['integer', 'min:'.$cupon->usos_actuales]], [
                'usos_maximos.min' => 'Ya se usó '.$cupon->usos_actuales.' veces; el límite no puede ser menor.',
            ]);
        }

        return [
            'codigo' => $data['codigo'],
            'nombre' => TextoPlano::linea($data['nombre']),
            'tipo' => $data['tipo'],
            'valor' => round((float) $data['valor'], 2),
            'compra_minima' => isset($data['compra_minima']) ? round((float) $data['compra_minima'], 2) : null,
            'usos_maximos' => $data['usos_maximos'] ?? null,
            'usos_por_usuario' => $data['usos_por_usuario'] ?? null,
            'inicia_en' => $this->fechaLocal($data['inicia_en'] ?? null),
            'termina_en' => $this->fechaLocal($data['termina_en'] ?? null),
            'activo' => $request->boolean('activo', true),
        ];
    }

    /** Las fechas del formulario están en hora de México. */
    private function fechaLocal(?string $valor): ?string
    {
        return $valor ? Carbon::parse($valor, 'America/Mexico_City')->setTimezone(config('app.timezone'))->toDateTimeString() : null;
    }

    private function vigencia(Cupon $c): string
    {
        return match (true) {
            ! $c->activo => 'inactivo',
            $c->termina_en !== null && $c->termina_en->isPast() => 'vencido',
            $c->inicia_en !== null && $c->inicia_en->isFuture() => 'programado',
            $c->usos_maximos !== null && $c->usos_actuales >= $c->usos_maximos => 'agotado',
            default => 'vigente',
        };
    }
}
