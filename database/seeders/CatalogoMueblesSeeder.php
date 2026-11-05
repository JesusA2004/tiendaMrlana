<?php

namespace Database\Seeders;

use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use Illuminate\Database\Seeder;

/**
 * Seeder SEGURO PARA PRODUCCIÓN: importa (idempotente) el catálogo de muebles 2026.
 * Por defecto quedan pendientes de aprobación. Con `CATALOGO_MUEBLES_PUBLICAR=true`
 * (config `catalogo.muebles_publicar`) además adjunta las fotos del catálogo y los publica
 * con el precio del catálogo. Nunca pisa precios, existencias ni ediciones del admin.
 */
class CatalogoMueblesSeeder extends Seeder
{
    public function run(ImportadorCatalogoMuebles $importador): void
    {
        $publicar = (bool) config('catalogo.muebles_publicar', false);

        $resultado = $importador->importar(imagenes: $publicar, publicar: $publicar);

        $this->command?->info('Catálogo de muebles: '.$resultado->resumen());

        if ($publicar) {
            $this->command?->info('Catálogo de muebles: '.$resultado->resumenVenta());
        }
    }
}
