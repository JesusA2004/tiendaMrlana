<?php

namespace App\Enums;

enum ModalidadVenta: string
{
    use EnumHelpers;

    case Inventario = 'inventario';
    case BajoPedido = 'bajo_pedido';

    public function label(): string
    {
        return match ($this) {
            self::Inventario => 'En inventario',
            self::BajoPedido => 'Bajo pedido',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::Inventario => 'success',
            self::BajoPedido => 'info',
        };
    }
}
