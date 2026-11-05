<?php

namespace App\Enums;

enum TipoMovimientoInventario: string
{
    use EnumHelpers;

    case Entrada = 'entrada';
    case Salida = 'salida';
    case Ajuste = 'ajuste';
    case Venta = 'venta';
    case Liberacion = 'liberacion';
    case Devolucion = 'devolucion';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::Ajuste => 'Ajuste',
            self::Venta => 'Venta (reserva)',
            self::Liberacion => 'Liberación',
            self::Devolucion => 'Devolución',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Entrada => 'success',
            self::Salida => 'warning',
            self::Ajuste => 'info',
            self::Venta => 'brand',
            self::Liberacion => 'neutral',
            self::Devolucion => 'info',
        };
    }
}
