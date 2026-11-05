<?php

namespace App\Enums;

enum EstadoPago: string
{
    use EnumHelpers;

    case Pendiente = 'pendiente';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case ContraEntrega = 'contra_entrega';
    case Reembolsado = 'reembolsado';
    case NoAplica = 'no_aplica';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente de pago',
            self::EnRevision => 'Comprobante en revisión',
            self::Aprobado => 'Pago aprobado',
            self::Rechazado => 'Pago rechazado',
            self::ContraEntrega => 'Pago contra entrega',
            self::Reembolsado => 'Reembolsado',
            self::NoAplica => 'No aplica',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::EnRevision => 'info',
            self::Aprobado => 'success',
            self::Rechazado => 'danger',
            self::ContraEntrega => 'info',
            self::Reembolsado => 'neutral',
            self::NoAplica => 'neutral',
        };
    }
}
