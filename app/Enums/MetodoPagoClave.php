<?php

namespace App\Enums;

enum MetodoPagoClave: string
{
    use EnumHelpers;

    case Transferencia = 'transferencia';
    case EfectivoPresencial = 'efectivo_presencial';
    case EfectivoContraEntrega = 'efectivo_contra_entrega';
    case Openpay = 'openpay';

    public function label(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia bancaria',
            self::EfectivoPresencial => 'Efectivo en tienda',
            self::EfectivoContraEntrega => 'Efectivo contra entrega',
            self::Openpay => 'Tarjeta (Openpay, deshabilitado)',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Transferencia => 'info',
            self::EfectivoPresencial => 'success',
            self::EfectivoContraEntrega => 'brand',
            self::Openpay => 'neutral',
        };
    }
}
