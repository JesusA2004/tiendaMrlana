<?php

namespace App\Enums;

enum EstadoPagoRegistro: string
{
    use EnumHelpers;

    case Pendiente = 'pendiente';
    case EnRevision = 'en_revision';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Cancelado = 'cancelado';
    case Reembolsado = 'reembolsado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnRevision => 'En revisión',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Rechazado',
            self::Cancelado => 'Cancelado',
            self::Reembolsado => 'Reembolsado',
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
            self::Cancelado => 'neutral',
            self::Reembolsado => 'neutral',
        };
    }
}
