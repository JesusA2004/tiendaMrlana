<?php

namespace App\Enums;

enum TipoReglaEnvio: string
{
    use EnumHelpers;

    case TarifaFija = 'tarifa_fija';
    case CotizacionManual = 'cotizacion_manual';
    case RecogerEnTienda = 'recoger_en_tienda';

    public function label(): string
    {
        return match ($this) {
            self::TarifaFija => 'Tarifa fija',
            self::CotizacionManual => 'Cotización manual',
            self::RecogerEnTienda => 'Recoger en tienda',
        };
    }

    /** Tono semántico para la UI: neutral|info|warning|success|danger|brand. */
    public function color(): string
    {
        return match ($this) {
            self::TarifaFija => 'success',
            self::CotizacionManual => 'warning',
            self::RecogerEnTienda => 'brand',
        };
    }
}
