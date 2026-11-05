<?php

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Oferta;
use App\Models\Producto;
use App\Services\Catalogo\PrecioService;
use Illuminate\Support\Facades\DB;

function oferta(array $attrs): Oferta
{
    return Oferta::create(array_merge([
        'nombre' => 'Oferta',
        'tipo' => 'porcentaje',
        'valor' => 10,
        'aplica_a' => 'productos',
        'activa' => true,
    ], $attrs));
}

it('devuelve el precio sin oferta', function () {
    $p = Producto::factory()->precio(1000)->create();

    $r = app(PrecioService::class)->calcular($p);

    expect($r['precio_original'])->toBe(1000.0)
        ->and($r['precio_final'])->toBe(1000.0)
        ->and($r['descuento'])->toBe(0.0)
        ->and($r['oferta'])->toBeNull();
});

it('devuelve nulos si el producto no tiene precio', function () {
    $p = Producto::factory()->borrador()->create();

    $r = app(PrecioService::class)->calcular($p);

    expect($r['precio_original'])->toBeNull()->and($r['precio_final'])->toBeNull();
});

it('la oferta directa del producto gana sobre la de categoría aunque sea menor', function () {
    $cat = Categoria::factory()->create();
    $p = Producto::factory()->precio(1000)->for($cat)->create();
    $directa = oferta(['valor' => 5]);
    $directa->productos()->attach($p);
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'valor' => 30]);

    $r = app(PrecioService::class)->calcular($p->fresh());

    expect($r['precio_final'])->toBe(950.0)->and($r['oferta']['id'])->toBe($directa->id);
});

it('entre categoría y marca elige el mayor descuento', function () {
    $cat = Categoria::factory()->create();
    $marca = Marca::factory()->create();
    $p = Producto::factory()->precio(1000)->for($cat)->create(['marca_id' => $marca->id]);
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'valor' => 10]);
    $deMarca = oferta(['aplica_a' => 'marca', 'marca_id' => $marca->id, 'tipo' => 'monto_fijo', 'valor' => 250]);

    $r = app(PrecioService::class)->calcular($p);

    expect($r['precio_final'])->toBe(750.0)
        ->and($r['descuento'])->toBe(250.0)
        ->and($r['oferta']['id'])->toBe($deMarca->id);
});

it('ignora ofertas vencidas, futuras o inactivas', function () {
    $cat = Categoria::factory()->create();
    $p = Producto::factory()->precio(1000)->for($cat)->create();
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'termina_en' => now()->subDay()]);
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'inicia_en' => now()->addDay()]);
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'activa' => false]);

    expect(app(PrecioService::class)->calcular($p)['precio_final'])->toBe(1000.0);
});

it('el descuento nunca deja el precio negativo', function () {
    $cat = Categoria::factory()->create();
    $p = Producto::factory()->precio(100)->for($cat)->create();
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id, 'tipo' => 'monto_fijo', 'valor' => 500]);

    expect(app(PrecioService::class)->calcular($p)['precio_final'])->toBe(0.0);
});

it('calcularMuchos no hace N+1 consultas', function () {
    $cat = Categoria::factory()->create();
    Producto::factory()->count(15)->for($cat)->create();
    oferta(['aplica_a' => 'categoria', 'categoria_id' => $cat->id]);
    $productos = Producto::query()->get();

    DB::enableQueryLog();
    $r = app(PrecioService::class)->calcularMuchos($productos);
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($r)->toHaveCount(15)
        ->and($r->first()['precio_final'])->toBe(900.0)
        ->and($consultas)->toBeLessThanOrEqual(2);
});
