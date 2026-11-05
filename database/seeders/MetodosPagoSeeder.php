<?php

namespace Database\Seeders;

use App\Enums\MetodoPagoClave;
use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

/**
 * Seeder de PRODUCCIÓN, idempotente. Crea los métodos que falten sin pisar lo que el admin
 * ya configuró (activo / instrucciones); sólo completa claves de configuración ausentes.
 * Openpay siempre queda inactivo y bloqueado mientras payments.enabled=false.
 */
class MetodosPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            MetodoPagoClave::Transferencia->value => [
                'nombre' => 'Transferencia bancaria',
                'activo' => true,
                'configuracion' => ['instrucciones' => null, 'horas_vencimiento' => 48],
            ],
            MetodoPagoClave::EfectivoPresencial->value => [
                'nombre' => 'Efectivo en tienda',
                'activo' => true,
                'configuracion' => ['instrucciones' => null, 'horas_vencimiento' => 48],
            ],
            MetodoPagoClave::EfectivoContraEntrega->value => [
                'nombre' => 'Efectivo contra entrega',
                'activo' => false,
                'configuracion' => ['instrucciones' => null, 'estados' => [], 'monto_maximo' => null],
            ],
            MetodoPagoClave::Openpay->value => [
                'nombre' => 'Tarjeta (Openpay)',
                'activo' => false,
                'configuracion' => ['bloqueado' => true],
            ],
        ];

        foreach ($metodos as $clave => $datos) {
            $metodo = MetodoPago::query()->firstOrNew(['clave' => $clave]);

            if (! $metodo->exists) {
                $metodo->fill($datos);
            } else {
                // Sólo agrega claves de configuración que no existan.
                $metodo->configuracion = array_merge($datos['configuracion'], (array) ($metodo->configuracion ?? []));
            }

            if ($clave === MetodoPagoClave::Openpay->value && ! config('payments.enabled', false)) {
                $metodo->activo = false;
                $metodo->configuracion = array_merge((array) $metodo->configuracion, ['bloqueado' => true]);
            }

            $metodo->save();
        }
    }
}
