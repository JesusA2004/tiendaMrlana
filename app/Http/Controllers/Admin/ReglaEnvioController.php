<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoReglaEnvio;
use App\Http\Controllers\Controller;
use App\Models\ReglaEnvio;
use App\Support\EstadosMexico;
use App\Support\TextoPlano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reglas de envío (tabla reglas_envio). Sin reglas activas, todo pedido a domicilio queda
 * «por cotizar». Nunca se anuncia envío gratis sin `gratis_desde`.
 */
class ReglaEnvioController extends Controller
{
    public function index(): Response
    {
        $reglas = ReglaEnvio::query()
            ->withCount('pedidos')
            ->orderByDesc('activo')
            ->orderByDesc('prioridad')
            ->orderBy('id')
            ->get()
            ->map(fn (ReglaEnvio $r) => [
                'id' => $r->id,
                'nombre' => $r->nombre,
                'tipo' => TipoReglaEnvio::tryFrom((string) $r->tipo)?->toArray() ?? ['value' => $r->tipo, 'label' => $r->tipo, 'color' => 'neutral'],
                'estados' => array_values((array) ($r->estados ?? [])),
                'cp_prefijos' => array_values((array) ($r->cp_prefijos ?? [])),
                'monto' => $r->monto !== null ? (float) $r->monto : null,
                'gratis_desde' => $r->gratis_desde !== null ? (float) $r->gratis_desde : null,
                'dias_estimados' => $r->dias_estimados,
                'permite_contra_entrega' => (bool) $r->permite_contra_entrega,
                'activo' => (bool) $r->activo,
                'prioridad' => (int) $r->prioridad,
                'notas' => $r->notas,
                'pedidos_count' => (int) $r->pedidos_count,
            ]);

        return Inertia::render('Admin/ReglasEnvio/Index', [
            'reglas' => $reglas,
            'tipos' => TipoReglaEnvio::options(),
            'estadosMexico' => EstadosMexico::LISTA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ReglaEnvio::create($this->validar($request));

        return back()->with('success', 'Regla de envío creada.');
    }

    public function update(Request $request, ReglaEnvio $reglaEnvio): RedirectResponse
    {
        $reglaEnvio->update($this->validar($request));

        return back()->with('success', 'Regla de envío actualizada.');
    }

    public function destroy(ReglaEnvio $reglaEnvio): RedirectResponse
    {
        // Los pedidos guardan la regla aplicada: si ya se usó, sólo se desactiva.
        if ($reglaEnvio->pedidos()->exists()) {
            $reglaEnvio->update(['activo' => false]);

            return back()->with('success', 'La regla ya se aplicó en pedidos; se desactivó en lugar de eliminarse.');
        }

        $reglaEnvio->delete();

        return back()->with('success', 'Regla de envío eliminada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $tipo = (string) $request->input('tipo');
        $esTarifa = $tipo === TipoReglaEnvio::TarifaFija->value;

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(TipoReglaEnvio::values())],
            'estados' => ['nullable', 'array', 'max:32'],
            'estados.*' => ['string', Rule::in(EstadosMexico::LISTA)],
            'cp_prefijos' => ['nullable', 'array', 'max:200'],
            'cp_prefijos.*' => ['string', 'regex:/^\d{1,5}$/'],
            'monto' => [$esTarifa ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'gratis_desde' => ['nullable', 'numeric', 'min:1', 'max:9999999.99'],
            'dias_estimados' => ['nullable', 'string', 'max:60'],
            'permite_contra_entrega' => ['boolean'],
            'activo' => ['boolean'],
            'prioridad' => ['required', 'integer', 'min:0', 'max:1000'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'monto.required' => 'La tarifa fija necesita un monto (puede ser 0).',
            'cp_prefijos.*.regex' => 'Cada prefijo de código postal debe tener de 1 a 5 dígitos.',
            'estados.*.in' => 'Selecciona estados de la lista.',
        ]);

        $recoger = $tipo === TipoReglaEnvio::RecogerEnTienda->value;

        return [
            'nombre' => TextoPlano::linea($data['nombre']),
            'tipo' => $tipo,
            'estados' => $recoger ? null : (array_values(array_unique($data['estados'] ?? [])) ?: null),
            'cp_prefijos' => $recoger ? null : (array_values(array_unique($data['cp_prefijos'] ?? [])) ?: null),
            // Cotización manual: el monto lo captura el equipo en cada pedido. Recoger: sin costo.
            'monto' => match ($tipo) {
                TipoReglaEnvio::TarifaFija->value => round((float) $data['monto'], 2),
                TipoReglaEnvio::RecogerEnTienda->value => 0,
                default => null,
            },
            'gratis_desde' => $esTarifa && isset($data['gratis_desde']) ? round((float) $data['gratis_desde'], 2) : null,
            'dias_estimados' => TextoPlano::limpiar($data['dias_estimados'] ?? null, false),
            'permite_contra_entrega' => ! $recoger && $request->boolean('permite_contra_entrega'),
            'activo' => $request->boolean('activo', true),
            'prioridad' => (int) $data['prioridad'],
            'notas' => TextoPlano::limpiar($data['notas'] ?? null),
        ];
    }
}
