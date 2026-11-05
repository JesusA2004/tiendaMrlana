<?php

namespace App\Services\Catalogo;

use App\Models\Oferta;
use App\Models\Producto;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Único lugar donde se calcula el precio de venta de un producto.
 *
 * Prioridad de ofertas: las asignadas directamente al producto ganan; si no hay,
 * se toma la de mayor descuento entre categoría y marca (empate → categoría).
 * Dentro de cada nivel gana la de mayor descuento.
 */
class PrecioService
{
    /**
     * @return array{precio_original: float|null, precio_final: float|null, descuento: float, oferta: array<string, mixed>|null, tiene_oferta: bool}
     */
    public function calcular(Producto $producto): array
    {
        return $this->calcularMuchos(new EloquentCollection([$producto]))->first();
    }

    /**
     * Calcula precios para muchos productos con 2 consultas como máximo
     * (ofertas directas si no están precargadas + ofertas de categoría/marca vigentes).
     *
     * @param  Collection<int, Producto>  $productos
     * @return Collection<int, array{precio_original: float|null, precio_final: float|null, descuento: float, oferta: array<string, mixed>|null, tiene_oferta: bool}> indexada por producto_id
     */
    public function calcularMuchos(Collection $productos): Collection
    {
        $productos = $productos->filter(fn ($p) => $p instanceof Producto)->values();

        if ($productos->isEmpty()) {
            return collect();
        }

        $eloquent = $productos instanceof EloquentCollection ? $productos : new EloquentCollection($productos->all());
        $eloquent->loadMissing('ofertas');

        $ahora = now();
        $categoriaIds = $eloquent->pluck('categoria_id')->filter()->unique()->values();
        $marcaIds = $eloquent->pluck('marca_id')->filter()->unique()->values();

        $ofertasGenerales = collect();

        if ($categoriaIds->isNotEmpty() || $marcaIds->isNotEmpty()) {
            $ofertasGenerales = Oferta::query()
                ->activas()
                ->where(function ($q) use ($categoriaIds, $marcaIds) {
                    $q->where(function ($q) use ($categoriaIds) {
                        $q->where('aplica_a', 'categoria')->whereIn('categoria_id', $categoriaIds->all() ?: [0]);
                    })->orWhere(function ($q) use ($marcaIds) {
                        $q->where('aplica_a', 'marca')->whereIn('marca_id', $marcaIds->all() ?: [0]);
                    });
                })
                ->get();
        }

        $porCategoria = $ofertasGenerales->where('aplica_a', 'categoria')->groupBy('categoria_id');
        $porMarca = $ofertasGenerales->where('aplica_a', 'marca')->groupBy('marca_id');

        return $eloquent->mapWithKeys(function (Producto $producto) use ($ahora, $porCategoria, $porMarca) {
            return [$producto->id => $this->resolver($producto, $ahora, $porCategoria, $porMarca)];
        });
    }

    /**
     * Descuento unitario que una oferta produce sobre un precio.
     */
    public function descuentoDeOferta(float $precio, Oferta $oferta): float
    {
        $tipo = self::normalizarTipo((string) $oferta->tipo);
        $valor = (float) $oferta->valor;

        $descuento = match ($tipo) {
            'porcentaje' => $precio * (min(100, max(0, $valor)) / 100),
            'monto_fijo', 'montofijo' => $valor,
            default => 0.0,
        };

        return round(min($precio, max(0, $descuento)), 2);
    }

    public static function normalizarTipo(string $tipo): string
    {
        $tipo = trim(mb_strtolower($tipo));

        return str_replace(['á', 'é', 'í', 'ó', 'ú', ' ', '-'], ['a', 'e', 'i', 'o', 'u', '_', '_'], $tipo);
    }

    /**
     * @param  Collection<int|string, Collection<int, Oferta>>  $porCategoria
     * @param  Collection<int|string, Collection<int, Oferta>>  $porMarca
     */
    private function resolver(Producto $producto, CarbonInterface $ahora, Collection $porCategoria, Collection $porMarca): array
    {
        if ($producto->precio === null) {
            return [
                'precio_original' => null,
                'precio_final' => null,
                'descuento' => 0.0,
                'oferta' => null,
                'tiene_oferta' => false,
            ];
        }

        $precio = round((float) $producto->precio, 2);

        $directas = $producto->ofertas->filter(fn (Oferta $o) => $this->vigente($o, $ahora));
        $mejor = $this->mejor($directas, $precio);

        if (! $mejor) {
            $generales = collect()
                ->merge($producto->categoria_id ? $porCategoria->get($producto->categoria_id, collect()) : [])
                ->merge($producto->marca_id ? $porMarca->get($producto->marca_id, collect()) : []);
            $mejor = $this->mejor($generales, $precio);
        }

        if (! $mejor) {
            return [
                'precio_original' => $precio,
                'precio_final' => $precio,
                'descuento' => 0.0,
                'oferta' => null,
                'tiene_oferta' => false,
            ];
        }

        [$oferta, $descuento] = $mejor;

        return [
            'precio_original' => $precio,
            'precio_final' => round($precio - $descuento, 2),
            'descuento' => $descuento,
            'oferta' => [
                'id' => $oferta->id,
                'nombre' => $oferta->nombre,
                'tipo' => $oferta->tipo,
                'valor' => (float) $oferta->valor,
                'aplica_a' => $oferta->aplica_a,
                'termina_en' => $oferta->termina_en?->toIso8601String(),
            ],
            'tiene_oferta' => true,
        ];
    }

    /**
     * @param  Collection<int, Oferta>  $ofertas
     * @return array{0: Oferta, 1: float}|null
     */
    private function mejor(Collection $ofertas, float $precio): ?array
    {
        $mejor = null;

        // Orden estable: categoría antes que marca, luego id descendente.
        $ordenadas = $ofertas->sortBy([
            fn (Oferta $a, Oferta $b) => ($a->aplica_a === 'marca') <=> ($b->aplica_a === 'marca'),
            fn (Oferta $a, Oferta $b) => $b->id <=> $a->id,
        ]);

        foreach ($ordenadas as $oferta) {
            $descuento = $this->descuentoDeOferta($precio, $oferta);

            if ($descuento > 0 && ($mejor === null || $descuento > $mejor[1])) {
                $mejor = [$oferta, $descuento];
            }
        }

        return $mejor;
    }

    private function vigente(Oferta $oferta, CarbonInterface $ahora): bool
    {
        return $oferta->activa
            && (! $oferta->inicia_en || $oferta->inicia_en->lte($ahora))
            && (! $oferta->termina_en || $oferta->termina_en->gte($ahora));
    }
}
