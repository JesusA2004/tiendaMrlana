<?php

namespace App\Enums;

enum ModalidadEntrega: string
{
    use EnumHelpers;

    case EnvioDomicilio = 'envio_domicilio';
    case RecogerEnTienda = 'recoger_en_tienda';

    public function label(): string
    {
        return match ($this) {
            self::EnvioDomicilio => 'Envío a domicilio',
            self::RecogerEnTienda => 'Recoger en tienda',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::EnvioDomicilio => 'info',
            self::RecogerEnTienda => 'brand',
        };
    }
}
