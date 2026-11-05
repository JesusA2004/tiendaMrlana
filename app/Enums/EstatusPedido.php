<?php

namespace App\Enums;

enum EstatusPedido: string
{
    use EnumHelpers;

    case PendienteCotizacion = 'pendiente_cotizacion';
    case EleccionEnvio = 'eleccion_envio';
    case PendientePago = 'pendiente_pago';
    case PagoEnRevision = 'pago_en_revision';
    case Confirmado = 'confirmado';
    case EnPreparacion = 'en_preparacion';
    case ListoEnvio = 'listo_envio';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';
    case Vencido = 'vencido';
    case Reembolsado = 'reembolsado';

    public function label(): string
    {
        return match ($this) {
            self::PendienteCotizacion => 'Pendiente de cotizar envío',
            self::EleccionEnvio => 'Esperando elección de envío',
            self::PendientePago => 'Pendiente de pago',
            self::PagoEnRevision => 'Pago en revisión',
            self::Confirmado => 'Confirmado',
            self::EnPreparacion => 'En preparación',
            self::ListoEnvio => 'Listo para envío',
            self::Enviado => 'Enviado',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
            self::Vencido => 'Vencido',
            self::Reembolsado => 'Reembolsado',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::PendienteCotizacion => 'warning',
            self::EleccionEnvio => 'info',
            self::PendientePago => 'warning',
            self::PagoEnRevision => 'info',
            self::Confirmado => 'success',
            self::EnPreparacion => 'info',
            self::ListoEnvio => 'info',
            self::Enviado => 'brand',
            self::Entregado => 'success',
            self::Cancelado => 'danger',
            self::Vencido => 'neutral',
            self::Reembolsado => 'neutral',
        };
    }
}
