<?php

namespace App\Enums;

enum EstadoLogistico: string
{
    use EnumHelpers;

    case Pendiente = 'pendiente';
    case EnPreparacion = 'en_preparacion';
    case ListoEnvio = 'listo_envio';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case ListoRecoger = 'listo_recoger';
    case Incidencia = 'incidencia';
    case Devuelto = 'devuelto';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnPreparacion => 'En preparación',
            self::ListoEnvio => 'Listo para envío',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
            self::ListoRecoger => 'Listo para recoger',
            self::Incidencia => 'Incidencia',
            self::Devuelto => 'Devuelto',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'neutral',
            self::EnPreparacion => 'info',
            self::ListoEnvio => 'info',
            self::Enviado => 'brand',
            self::Entregado => 'success',
            self::ListoRecoger => 'brand',
            self::Incidencia => 'danger',
            self::Devuelto => 'neutral',
        };
    }
}
