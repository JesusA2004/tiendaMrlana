<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use App\Services\Catalogo\Importacion\ResultadoImportacion;
use Illuminate\Console\Command;
use Throwable;

/**
 * Importa los modelos del catálogo PDF de muebles 2026. Idempotente: puede ejecutarse cuantas veces sea necesario.
 *
 * Sin opciones: pendientes de aprobación, sin precio de venta ni imágenes.
 * `--imagenes --publicar`: adjunta las fotos del catálogo, usa el precio del catálogo como precio de venta
 * (sólo si no hay precio) y publica en la tienda (requiere `php artisan storage:link`).
 */
class ImportarCatalogoMuebles extends Command
{
    protected $signature = 'catalogo:importar-muebles
        {--dry-run : Muestra lo que haría sin escribir en la base de datos}
        {--imagenes : Adjunta las fotos del catálogo a los modelos que aún no tienen imágenes}
        {--publicar : Precio del catálogo si no hay precio, bajo pedido si no hay existencias, y publica en la tienda}
        {--forzar-precios : Re-sincroniza el precio de venta con el precio del catálogo aunque ya tenga uno}';

    protected $description = 'Importa el catálogo de muebles 2026 (con --imagenes --publicar lo deja visible en la tienda)';

    public function handle(ImportadorCatalogoMuebles $importador): int
    {
        $simulacion = (bool) $this->option('dry-run');
        $imagenes = (bool) $this->option('imagenes');
        $publicar = (bool) $this->option('publicar');
        $forzarPrecios = (bool) $this->option('forzar-precios');

        try {
            $resultado = $importador->importar($simulacion, null, $imagenes, $publicar, $forzarPrecios);
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

        if (! $imagenes && ! $publicar && ! $forzarPrecios) {
            $this->line('Los productos importados quedan «pendientes de aprobación», ocultos, sin precio de venta, sin existencias y sin imágenes.');
            $this->line('Para publicarlos con las fotos y precios del catálogo: php artisan catalogo:importar-muebles --imagenes --publicar');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['SKU', 'Producto', 'Cambios', 'Pendiente'],
            array_map(fn (array $v) => [
                $v['sku'],
                $v['nombre'],
                $v['cambios'] === [] ? '<fg=gray>sin cambios</>' : implode(', ', $v['cambios']),
                implode(' ', $v['avisos']),
            ], $resultado->venta),
        );

        $this->info($resultado->resumenVenta());

        foreach ($resultado->noPublicados() as $v) {
            $this->warn("No publicado {$v['sku']} ({$v['nombre']}): ".implode(' ', $v['avisos']));
        }

        if (! $simulacion) {
            $this->line(sprintf(
                'Productos visibles en la tienda: %d (de %d importados del catálogo).',
                Producto::query()->publicados()->where('importacion_origen', ImportadorCatalogoMuebles::ORIGEN)->count(),
                Producto::query()->where('importacion_origen', ImportadorCatalogoMuebles::ORIGEN)->count(),
            ));
        }

        return self::SUCCESS;
    }
}
