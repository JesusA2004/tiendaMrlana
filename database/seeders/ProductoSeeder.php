<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Productos de demostración (sólo APP_ENV=local).
 */
class ProductoSeeder extends Seeder
{
    public function run(): void
    {
        // Datos de DEMOSTRACIÓN: nunca en producción ni desde DatabaseSeeder.
        if (! app()->environment('local')) {
            $this->command?->warn('ProductoSeeder sólo se ejecuta en entorno local.');

            return;
        }

        $categoria = Categoria::firstOrCreate(
            ['slug' => 'sartenes'],
            [
                'nombre' => 'Sartenes',
                'descripcion' => 'Sartenes de titanio',
                'activa' => true,
                'orden' => 1,
            ]
        );

        $productos = [
            [
                'nombre' => 'Sartén de titanio puro 30 cm',
                'sku' => 'SAR-001',
                'precio' => 716.56,
                'precio_comparacion' => 899.00,
                'imagen_principal' => '/storage/productos/sarten-1.jpg',
                'destacado' => true,
            ],
            [
                'nombre' => 'Sartén de titanio premium 28 cm',
                'sku' => 'SAR-002',
                'precio' => 600.52,
                'precio_comparacion' => 780.00,
                'imagen_principal' => '/storage/productos/sarten-2.jpg',
                'destacado' => true,
            ],
            [
                'nombre' => 'Sartén de titanio negro 30 cm',
                'sku' => 'SAR-003',
                'precio' => 693.03,
                'precio_comparacion' => 850.00,
                'imagen_principal' => '/storage/productos/sarten-3.jpg',
                'destacado' => false,
            ],
            [
                'nombre' => 'Sartén de titanio clásico 30 cm',
                'sku' => 'SAR-004',
                'precio' => 625.19,
                'precio_comparacion' => 790.00,
                'imagen_principal' => '/storage/productos/sarten-4.jpg',
                'destacado' => true,
            ],
        ];

        foreach ($productos as $item) {
            Producto::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'categoria_id' => $categoria->id,
                    'nombre' => $item['nombre'],
                    'slug' => Str::slug($item['nombre']),
                    'descripcion' => 'Producto de demostración para la tienda.',
                    'precio' => $item['precio'],
                    'precio_comparacion' => $item['precio_comparacion'],
                    'stock' => 10,
                    'imagen_principal' => $item['imagen_principal'],
                    'destacado' => $item['destacado'],
                    'visible' => true,
                    'activo' => true,
                    'estado_publicacion' => 'publicado',
                    'modalidad_venta' => 'inventario',
                ]
            );
        }
    }
}
