<?php

namespace App\Console\Commands;

use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use App\Services\Catalogo\Importacion\ResultadoImportacion;
use Illuminate\Console\Command;
use Throwable;

/**
 * Importa los modelos del catálogo PDF de muebles 2026 como productos pendientes de aprobación.
 * Idempotente: puede ejecutarse cuantas veces sea necesario.
 */
class ImportarCatalogoMuebles extends Command
{
    protected $signature = 'catalogo:importar-muebles
        {--dry-run : Muestra lo que haría sin escribir en la base de datos}';

    protected $description = 'Importa el catálogo de muebles 2026 (pendiente de aprobación, sin precio de venta ni imágenes)';

    public function handle(ImportadorCatalogoMuebles $importador): int
    {
        $simulacion = (bool) $this->option('dry-run');

        try {
            $resultado = $importador->importar($simulacion);
        } catch (Throwable $e) {
            $this->error('No se pudo importar el catálogo: '.$e->getMessage());

            return self::FAILURE;
        }

        $etiquetas = [
            ResultadoImportacion::CREADO => '<fg=green>creado</>',
            ResultadoImportacion::ACTUALIZADO => '<fg=yellow>actualizado</>',
            ResultadoImportacion::SIN_CAMBIOS => '<fg=gray>sin cambios</>',
        ];

        $this->table(
            ['SKU', 'Producto', 'Resultado', 'Campos'],
            array_map(fn (array $p) => [
                $p['sku'],
                $p['nombre'],
                $etiquetas[$p['accion']] ?? $p['accion'],
                implode(', ', $p['campos']),
            ], $resultado->productos),
        );

        if ($resultado->categoriasCreadas !== []) {
            $this->line('Categorías '.($simulacion ? 'por crear' : 'creadas').': '.implode(', ', $resultado->categoriasCreadas));
        }

        $this->info($resultado->resumen());
        $this->line('Los productos importados quedan «pendientes de aprobación», ocultos, sin precio de venta, sin existencias y sin imágenes.');

        return self::SUCCESS;
    }
}
