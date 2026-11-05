<?php

namespace App\Models;

use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Database\Eloquent\Model;

/**
 * Par clave → valor. Leer/escribir preferentemente vía App\Services\Configuracion\ConfiguracionTienda.
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
    ];

    protected static function booted(): void
    {
        // Cualquier escritura directa invalida la caché de ConfiguracionTienda.
        $olvidar = fn () => app(ConfiguracionTienda::class)->olvidar();

        static::saved($olvidar);
        static::deleted($olvidar);
    }
}
