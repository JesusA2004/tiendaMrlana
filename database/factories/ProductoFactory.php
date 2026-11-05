<?php

namespace Database\Factories;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Por defecto genera un producto PUBLICADO, vendible, con imagen y stock.
 *
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $nombre = ucfirst($this->faker->unique()->words(3, true));

        return [
            'categoria_id' => Categoria::factory(),
            'marca_id' => null,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.Str::lower(Str::random(5)),
            'descripcion' => $this->faker->sentence(),
            'precio' => 1000,
            'stock' => 10,
            'stock_minimo' => 0,
            'imagen_principal' => 'productos/demo.jpg',
            'destacado' => false,
            'visible' => true,
            'activo' => true,
            'estado_publicacion' => EstadoPublicacion::Publicado->value,
            'modalidad_venta' => ModalidadVenta::Inventario->value,
            'publicado_en' => now(),
        ];
    }

    public function precio(float $precio): static
    {
        return $this->state(['precio' => $precio]);
    }

    public function stock(int $stock): static
    {
        return $this->state(['stock' => $stock]);
    }

    public function agotado(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function bajoPedido(int $dias = 15): static
    {
        return $this->state([
            'modalidad_venta' => ModalidadVenta::BajoPedido->value,
            'tiempo_entrega_dias' => $dias,
            'stock' => 0,
        ]);
    }

    public function borrador(): static
    {
        return $this->state([
            'estado_publicacion' => EstadoPublicacion::Borrador->value,
            'precio' => null,
            'imagen_principal' => null,
            'publicado_en' => null,
        ]);
    }

    public function pendienteAprobacion(): static
    {
        return $this->state([
            'estado_publicacion' => EstadoPublicacion::PendienteAprobacion->value,
            'visible' => false,
            'precio' => null,
            'stock' => 0,
            'imagen_principal' => null,
        ]);
    }

    public function pausado(): static
    {
        return $this->state(['estado_publicacion' => EstadoPublicacion::Pausado->value]);
    }
}
