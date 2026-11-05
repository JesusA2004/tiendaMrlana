<?php

namespace App\Enums;

/**
 * Estado del costo de envío del pedido:
 * calculado (regla automática) | por_cotizar (falta cotizar) |
 * opciones_enviadas (el cliente debe elegir paquetería) | cotizado (costo final fijado).
 */
enum EstadoEnvio: string
{
    use EnumHelpers;

    case Calculado = 'calculado';
    case PorCotizar = 'por_cotizar';
    case OpcionesEnviadas = 'opciones_enviadas';
    case Cotizado = 'cotizado';

    public function label(): string
    {
        return match ($this) {
            self::Calculado => 'Calculado',
            self::PorCotizar => 'Por cotizar',
            self::OpcionesEnviadas => 'Esperando elección del cliente',
            self::Cotizado => 'Cotizado',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Calculado => 'success',
            self::PorCotizar => 'warning',
            self::OpcionesEnviadas => 'info',
            self::Cotizado => 'info',
        };
    }

    /** Valores en los que el total aún no es final (no se puede pagar). */
    public static function pendientes(): array
    {
        return [self::PorCotizar->value, self::OpcionesEnviadas->value];
    }
}
