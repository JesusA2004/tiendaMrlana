<?php

namespace Database\Seeders;

use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use Illuminate\Database\Seeder;

/**
 * Seeder SEGURO PARA PRODUCCIÓN: importa (idempotente) el catálogo de muebles 2026 como
 * productos pendientes de aprobación. Nunca pisa precios, existencias ni ediciones del admin.
 */
class CatalogoMueblesSeeder extends Seeder
{
    public function run(ImportadorCatalogoMuebles $importador): void
    {
        $resultado = $importador->importar();

        $this->command?->info('Catálogo de muebles: '.$resultado->resumen());
    }
}
