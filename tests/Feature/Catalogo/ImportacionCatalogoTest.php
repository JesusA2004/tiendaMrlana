<?php

use App\Enums\EstadoPublicacion;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use Database\Seeders\CatalogoMueblesSeeder;

it('importa los 25 modelos como pendientes, ocultos, sin precio, sin stock y sin imágenes', function () {
    $this->artisan('catalogo:importar-muebles')
        ->expectsOutputToContain('25 creados, 0 actualizados, 0 sin cambios')
        ->assertSuccessful();

    expect(Producto::count())->toBe(25)
        ->and(Producto::where('estado_publicacion', EstadoPublicacion::PendienteAprobacion->value)->count())->toBe(25)
        ->and(Producto::where('visible', true)->count())->toBe(0)
        ->and(Producto::whereNotNull('precio')->count())->toBe(0)
        ->and(Producto::where('stock', '!=', 0)->count())->toBe(0)
        ->and(Producto::whereNotNull('imagen_principal')->count())->toBe(0)
        ->and(Producto::whereNotNull('marca_id')->count())->toBe(0)
        ->and(Producto::has('imagenes')->count())->toBe(0);

    $lack = Producto::where('codigo_referencia', '90243297')->firstOrFail();

    expect($lack->sku)->toBe('ML-MUE-90243297')
        ->and($lack->slug)->toBe('mueble-de-tv-lack')
        ->and($lack->modelo)->toBe('LACK')
        ->and($lack->acabado)->toBe('negro')
        ->and($lack->dimensiones)->toBe('90x26x45 cm')
        ->and((float) $lack->precio_referencia)->toBe(699.0)
        ->and($lack->referencia_consultada_en->toDateString())->toBe('2026-11-04')
        ->and($lack->fuente_referencia)->toContain('https://www.ikea.com/mx/es/p/lack-mueble-de-tv-negro-90243297/')
        ->and($lack->descripcion)->toStartWith('Para tu zona de entretenimiento.')
        ->and($lack->importacion_origen)->toBe('catalogo_muebles_mx_2026')
        ->and($lack->importacion_clave)->toBe('catalogo_muebles_mx_2026:90243297')
        ->and($lack->categoria->nombre)->toBe('Sala')
        ->and($lack->categoria->padre->nombre)->toBe('Muebles');

    // Los dos KALLAX comparten nombre: slugs distintos y estables.
    expect(Producto::where('modelo', 'KALLAX')->pluck('slug')->unique())->toHaveCount(2);

    expect(Categoria::whereIn('slug', ['sala', 'comedor', 'recamara', 'home-office', 'organizacion', 'terraza'])->count())->toBe(6);
});

it('es idempotente: la segunda corrida no crea ni cambia nada', function () {
    $this->artisan('catalogo:importar-muebles')->assertSuccessful();
    $antes = Producto::orderBy('id')->get()->map->only(['id', 'sku', 'slug', 'updated_at'])->all();

    $this->artisan('catalogo:importar-muebles')
        ->expectsOutputToContain('0 creados, 0 actualizados, 25 sin cambios')
        ->assertSuccessful();

    expect(Producto::count())->toBe(25)
        ->and(Categoria::count())->toBe(7)
        ->and(Producto::orderBy('id')->get()->map->only(['id', 'sku', 'slug', 'updated_at'])->all())->toEqual($antes);
});

it('el seeder de producción importa y es idempotente', function () {
    $this->seed(CatalogoMueblesSeeder::class);
    $this->seed(CatalogoMueblesSeeder::class);

    expect(Producto::count())->toBe(25)
        ->and(Producto::where('estado_publicacion', 'pendiente_aprobacion')->count())->toBe(25);

    // DatabaseSeeder (producción) también lo ejecuta.
    $this->seed();
    expect(Producto::count())->toBe(25);
});

it('dry-run no escribe nada', function () {
    $this->artisan('catalogo:importar-muebles', ['--dry-run' => true])
        ->expectsOutputToContain('[Simulación] 25 creados')
        ->assertSuccessful();

    expect(Producto::count())->toBe(0)->and(Categoria::count())->toBe(0);
});

it('reimportar no pisa ediciones del admin y sólo actualiza datos de referencia', function () {
    $importador = app(ImportadorCatalogoMuebles::class);
    $importador->importar();

    $producto = Producto::where('codigo_referencia', '90243297')->firstOrFail();
    $otraCategoria = Categoria::factory()->create();
    $producto->forceFill([
        'precio' => 1299,
        'nombre' => 'Mueble TV negro Mr Lana',
        'descripcion' => 'Texto propio',
        'categoria_id' => $otraCategoria->id,
        'acabado' => null,
        'estado_publicacion' => EstadoPublicacion::Pausado->value,
        'precio_referencia' => 500,
    ])->save();

    $resultado = $importador->importar();

    $producto->refresh();
    expect((float) $producto->precio)->toBe(1299.0)
        ->and($producto->nombre)->toBe('Mueble TV negro Mr Lana')
        ->and($producto->descripcion)->toBe('Texto propio')
        ->and($producto->categoria_id)->toBe($otraCategoria->id)
        ->and($producto->estado_publicacion)->toBe('pausado')
        ->and($producto->acabado)->toBe('negro') // se llenó porque estaba vacío
        ->and((float) $producto->precio_referencia)->toBe(699.0) // dato de referencia sincronizado
        ->and($resultado->creados())->toBe(0)
        ->and($resultado->actualizados())->toBe(1)
        ->and($resultado->sinCambios())->toBe(24)
        ->and(Producto::count())->toBe(25);
});

it('los productos importados no aparecen en la tienda pública', function () {
    app(ImportadorCatalogoMuebles::class)->importar();
    $producto = Producto::where('codigo_referencia', '90243297')->firstOrFail();

    $this->get('/productos')
        ->assertOk()
        ->assertDontSee('Mueble de TV LACK')
        ->assertDontSee('90243297');

    $this->get('/productos/'.$producto->slug)->assertNotFound();
});
