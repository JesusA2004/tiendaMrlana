<?php

namespace App\Enums;

enum EstadoPublicacion: string
{
    use EnumHelpers;

    case Borrador = 'borrador';
    case PendienteAprobacion = 'pendiente_aprobacion';
    case Publicado = 'publicado';
    case Pausado = 'pausado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::PendienteAprobacion => 'Pendiente de aprobación',
            self::Publicado => 'Publicado',
            self::Pausado => 'Pausado',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'neutral',
            self::PendienteAprobacion => 'warning',
            self::Publicado => 'success',
            self::Pausado => 'neutral',
        };
    }
}
