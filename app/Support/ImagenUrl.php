<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Convierte rutas guardadas (disco public, /storage/..., URLs absolutas) en URL pública.
 */
final class ImagenUrl
{
    public static function resolver(?string $ruta): ?string
    {
        $ruta = $ruta !== null ? trim($ruta) : null;

        if ($ruta === null || $ruta === '') {
            return null;
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://') || str_starts_with($ruta, '/storage/')) {
            return $ruta;
        }

        if (str_starts_with($ruta, 'storage/')) {
            return '/'.$ruta;
        }

        return Storage::disk('public')->url($ruta);
    }
}
