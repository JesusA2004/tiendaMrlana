<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MetodoPagoClave;
use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Support\EstadosMexico;
use App\Support\TextoPlano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Métodos de pago del sistema (transferencia, efectivo presencial, contra entrega y Openpay
 * bloqueado). Se administran desde la pestaña «Métodos de pago» de Configuración.
 */
class MetodoPagoController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('admin.configuraciones.index', ['tab' => 'metodos']);
    }

    public function update(Request $request, MetodoPago $metodoPago, ConfiguracionTienda $config): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'clave' => ['required', 'string', 'max:50', Rule::unique('metodos_pagos', 'clave')->ignore($metodoPago->id)],
            'activo' => ['nullable', 'boolean'],
            'configuracion' => ['nullable', 'array'],
            'configuracion.instrucciones' => ['nullable', 'string', 'max:1000'],
            'configuracion.horas_vencimiento' => ['nullable', 'integer', 'min:1', 'max:720'],
            'configuracion.estados' => ['nullable', 'array', 'max:32'],
            'configuracion.estados.*' => ['string', Rule::in(EstadosMexico::LISTA)],
            'configuracion.monto_maximo' => ['nullable', 'numeric', 'min:1', 'max:9999999.99'],
        ], [
            'configuracion.horas_vencimiento.min' => 'El vencimiento debe ser de al menos 1 hora.',
            'configuracion.horas_vencimiento.max' => 'El vencimiento no puede pasar de 720 horas (30 días).',
        ]);

        $clave = Str::slug(trim($data['clave']), '_');
        $activo = $request->boolean('activo', (bool) $metodoPago->activo);

        if ($metodoPago->esClaveDeSistema() && $clave !== $metodoPago->clave) {
            throw ValidationException::withMessages(['clave' => 'La clave de este método la usa el sistema y no puede cambiarse.']);
        }

        $this->validarBloqueoPagosEnLinea($clave, $activo);

        // Sólo al activarla: si ya estaba activa (seeder) se permite editar instrucciones/vencimiento.
        if ($activo && ! $metodoPago->activo && $clave === MetodoPagoClave::Transferencia->value && ! $config->bancoConfigurado()) {
            throw ValidationException::withMessages([
                'activo' => 'Configura banco, beneficiario y CLABE antes de activar la transferencia.',
            ]);
        }

        $configuracion = (array) ($metodoPago->configuracion ?? []);
        $entrada = (array) ($data['configuracion'] ?? []);

        if (array_key_exists('instrucciones', $entrada)) {
            $configuracion['instrucciones'] = TextoPlano::limpiar($entrada['instrucciones']);
        }

        if (in_array($clave, [MetodoPagoClave::Transferencia->value, MetodoPagoClave::EfectivoPresencial->value], true)
            && array_key_exists('horas_vencimiento', $entrada)) {
            $configuracion['horas_vencimiento'] = $entrada['horas_vencimiento'] !== null ? (int) $entrada['horas_vencimiento'] : 48;
        }

        if ($clave === MetodoPagoClave::EfectivoContraEntrega->value) {
            if (array_key_exists('estados', $entrada)) {
                $configuracion['estados'] = array_values(array_unique((array) ($entrada['estados'] ?? [])));
            }

            if (array_key_exists('monto_maximo', $entrada)) {
                $configuracion['monto_maximo'] = $entrada['monto_maximo'] !== null ? round((float) $entrada['monto_maximo'], 2) : null;
            }
        }

        if ($clave === MetodoPagoClave::Openpay->value) {
            $configuracion['bloqueado'] = ! config('payments.enabled', false);
        }

        $metodoPago->update([
            'nombre' => TextoPlano::linea($data['nombre']),
            'clave' => $clave,
            'activo' => $activo,
            'configuracion' => $configuracion,
        ]);

        return back()->with('success', 'Método de pago «'.$metodoPago->nombre.'» actualizado.');
    }

    /**
     * Los pagos en línea (Openpay) no pueden activarse mientras config('payments.enabled') sea false.
     */
    private function validarBloqueoPagosEnLinea(string $clave, bool $activo): void
    {
        if ($activo && $clave === MetodoPagoClave::Openpay->value && ! config('payments.enabled', false)) {
            throw ValidationException::withMessages([
                'activo' => 'Los pagos con tarjeta (Openpay) están deshabilitados en esta tienda y no pueden activarse.',
            ]);
        }
    }
}
