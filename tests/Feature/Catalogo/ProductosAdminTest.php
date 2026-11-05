<?php

use App\Enums\EstadoPublicacion;
use App\Models\Categoria;
use App\Models\InventarioMovimiento;
use App\Models\Marca;
use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function productoPendiente(array $attrs = []): Producto
{
    return Producto::factory()->pendienteAprobacion()->create($attrs);
}

it('vendedor y cliente reciben 403 en el catálogo admin', function (string $rol) {
    $usuario = usuarioConRol($rol);
    $producto = Producto::factory()->create();

    $this->actingAs($usuario)->get('/admin/productos')->assertForbidden();
    $this->actingAs($usuario)->get('/admin/inventario')->assertForbidden();
    $this->actingAs($usuario)->get('/admin/categorias')->assertForbidden();
    $this->actingAs($usuario)->get('/admin/marcas')->assertForbidden();
    $this->actingAs($usuario)->post("/admin/productos/{$producto->id}/publicar")->assertForbidden();
    $this->actingAs($usuario)->post('/admin/inventario/ajustes', [
        'producto_id' => $producto->id, 'operacion' => 'entrada', 'cantidad' => 5, 'motivo' => 'Compra',
    ])->assertForbidden();
    $this->actingAs($usuario)->post('/admin/productos/importar-catalogo')->assertForbidden();
})->with(['vendedor', 'cliente']);

it('el admin ve el listado con filtros y la cola de aprobación', function () {
    $admin = adminTienda();
    productoPendiente(['nombre' => 'Mesa pendiente', 'codigo_referencia' => 'ABC123']);
    Producto::factory()->create(['nombre' => 'Silla publicada']);

    $this->actingAs($admin)->get('/admin/productos?estado=pendiente_aprobacion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Productos/Index')
            ->has('productos.data', 1)
            ->where('productos.data.0.nombre', 'Mesa pendiente')
            ->where('productos.data.0.completitud', 50)
            ->where('conteos.pendiente_aprobacion', 1)
            ->where('conteos.publicado', 1));

    $this->actingAs($admin)->get('/admin/productos?q=ABC123')
        ->assertInertia(fn (Assert $page) => $page->has('productos.data', 1));
});

it('no se puede publicar sin precio ni imagen', function () {
    $admin = adminTienda();
    $producto = productoPendiente(['categoria_id' => Categoria::factory()->create()->id]);

    $this->actingAs($admin)
        ->from("/admin/productos/{$producto->id}/editar")
        ->post("/admin/productos/{$producto->id}/publicar")
        ->assertRedirect("/admin/productos/{$producto->id}/editar")
        ->assertSessionHas('error')
        ->assertSessionHasErrors('publicacion');

    expect($producto->fresh()->estado_publicacion)->toBe('pendiente_aprobacion');

    $producto->forceFill(['precio' => 2500])->save();
    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/publicar")->assertSessionHas('error');
    expect($producto->fresh()->estado_publicacion)->toBe('pendiente_aprobacion');
});

it('publicar con datos completos lo muestra en la tienda y registra la aprobación', function () {
    Storage::fake('public');
    $admin = adminTienda();
    $producto = productoPendiente(['categoria_id' => Categoria::factory()->create()->id, 'nombre' => 'Librero Nogal']);

    $this->actingAs($admin)->patch("/admin/productos/{$producto->id}/rapido", [
        'precio' => 3499, 'categoria_id' => $producto->categoria_id, 'modalidad_venta' => 'bajo_pedido', 'tiempo_entrega_dias' => 10,
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/imagenes", [
        'imagenes' => [UploadedFile::fake()->image('librero.jpg', 800, 600)],
        'derechos' => '1',
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/publicar")->assertSessionHas('success');

    $producto->refresh();
    expect($producto->estado_publicacion)->toBe('publicado')
        ->and($producto->visible)->toBeTrue()
        ->and($producto->aprobado_por)->toBe($admin->id)
        ->and($producto->aprobado_en)->not->toBeNull()
        ->and($producto->publicado_en)->not->toBeNull();

    $this->get('/productos/'.$producto->slug)->assertOk();
    $this->get('/productos')->assertOk()->assertSee('Librero Nogal');

    // Pausar lo retira de la tienda.
    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/pausar")->assertSessionHas('success');
    $this->get('/productos/'.$producto->slug)->assertNotFound();
});

it('sube imágenes a WebP sólo con confirmación de derechos y valida el tipo', function () {
    Storage::fake('public');
    $admin = adminTienda();
    $producto = productoPendiente();

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/imagenes", [
        'imagenes' => [UploadedFile::fake()->image('a.jpg', 600, 600)],
    ])->assertSessionHasErrors('derechos');

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/imagenes", [
        'imagenes' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        'derechos' => '1',
    ])->assertSessionHasErrors('imagenes.0');

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/imagenes", [
        'imagenes' => [UploadedFile::fake()->image('grande.png', 600, 600)->size(6000)],
        'derechos' => '1',
    ])->assertSessionHasErrors('imagenes.0');

    expect($producto->imagenes()->count())->toBe(0);

    $this->actingAs($admin)->post("/admin/productos/{$producto->id}/imagenes", [
        'imagenes' => [UploadedFile::fake()->image('a.jpg', 2400, 1200), UploadedFile::fake()->image('b.png', 400, 400)],
        'derechos' => '1',
    ])->assertSessionHas('success');

    $imagenes = $producto->imagenes()->orderBy('orden')->get();
    expect($imagenes)->toHaveCount(2)
        ->and($imagenes[0]->principal)->toBeTrue()
        ->and($imagenes[1]->principal)->toBeFalse();

    Storage::disk('public')->assertExists($imagenes[0]->ruta);

    if (function_exists('imagewebp')) {
        expect($imagenes[0]->ruta)->toEndWith('.webp');
        [$ancho] = getimagesizefromstring(Storage::disk('public')->get($imagenes[0]->ruta));
        expect($ancho)->toBe(1600);
    }

    // Reordenar y elegir principal.
    $this->actingAs($admin)->put("/admin/productos/{$producto->id}/imagenes/orden", ['ids' => [$imagenes[1]->id, $imagenes[0]->id]])
        ->assertSessionHas('success');
    expect($imagenes[1]->fresh()->orden)->toBe(1);

    $this->actingAs($admin)->patch("/admin/productos/{$producto->id}/imagenes/{$imagenes[1]->id}/principal")->assertSessionHas('success');
    expect($imagenes[1]->fresh()->principal)->toBeTrue()
        ->and($imagenes[0]->fresh()->principal)->toBeFalse()
        ->and($producto->fresh()->imagen_principal)->toBe($imagenes[1]->ruta);

    // Eliminar borra el archivo y reasigna la principal.
    $this->actingAs($admin)->delete("/admin/productos/{$producto->id}/imagenes/{$imagenes[1]->id}")->assertSessionHas('success');
    Storage::disk('public')->assertMissing($imagenes[1]->ruta);
    expect($imagenes[0]->fresh()->principal)->toBeTrue();

    // Una imagen de otro producto no se puede manipular desde este.
    $otra = Producto::factory()->create()->imagenes()->create(['ruta' => 'productos/x.webp', 'orden' => 1]);
    $this->actingAs($admin)->delete("/admin/productos/{$producto->id}/imagenes/{$otra->id}")->assertNotFound();
});

it('el ajuste de inventario crea un movimiento vía InventarioService', function () {
    $admin = adminTienda();
    $producto = Producto::factory()->stock(3)->create();

    $this->actingAs($admin)->post('/admin/inventario/ajustes', [
        'producto_id' => $producto->id, 'operacion' => 'entrada', 'cantidad' => 7, 'motivo' => 'Compra a proveedor',
    ])->assertSessionHas('success');

    expect($producto->fresh()->stock)->toBe(10);
    $mov = InventarioMovimiento::latest('id')->first();
    expect($mov->tipo)->toBe('entrada')
        ->and($mov->cantidad)->toBe(7)
        ->and($mov->stock_anterior)->toBe(3)
        ->and($mov->stock_nuevo)->toBe(10)
        ->and($mov->user_id)->toBe($admin->id)
        ->and($mov->motivo)->toBe('Compra a proveedor');

    $this->actingAs($admin)->post('/admin/inventario/ajustes', [
        'producto_id' => $producto->id, 'operacion' => 'establecer', 'cantidad' => 4, 'motivo' => 'Conteo físico',
    ])->assertSessionHas('success');
    expect($producto->fresh()->stock)->toBe(4)
        ->and(InventarioMovimiento::latest('id')->first()->cantidad)->toBe(-6);

    // No permite dejar stock negativo y exige motivo.
    $this->actingAs($admin)->post('/admin/inventario/ajustes', [
        'producto_id' => $producto->id, 'operacion' => 'salida', 'cantidad' => 50, 'motivo' => 'Merma',
    ])->assertSessionHas('error');
    $this->actingAs($admin)->post('/admin/inventario/ajustes', [
        'producto_id' => $producto->id, 'operacion' => 'entrada', 'cantidad' => 1,
    ])->assertSessionHasErrors('motivo');

    expect($producto->fresh()->stock)->toBe(4);

    $this->actingAs($admin)->get('/admin/inventario?tipo=entrada&desde=2020-01-01&hasta=2099-12-31')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventario/Index')->has('movimientos.data', 1));
});

it('el formulario de producto no modifica existencias y valida el precio de comparación', function () {
    $admin = adminTienda();
    $producto = Producto::factory()->stock(5)->create();

    $base = [
        'nombre' => 'Mesa de centro', 'modalidad_venta' => 'inventario', 'precio' => 1500,
        'categoria_id' => $producto->categoria_id, 'stock' => 999, 'destacado' => false,
    ];

    $this->actingAs($admin)->put("/admin/productos/{$producto->id}", [...$base, 'precio_comparacion' => 1200])
        ->assertSessionHasErrors('precio_comparacion');

    $this->actingAs($admin)->put("/admin/productos/{$producto->id}", [
        ...$base,
        'precio_comparacion' => 1800,
        'descripcion' => '<script>alert(1)</script>Mesa <b>robusta</b>',
        'atributos' => [['nombre' => 'Material', 'valor' => 'Pino'], ['nombre' => '', 'valor' => '']],
    ])->assertSessionHasNoErrors();

    $producto->refresh();
    expect($producto->stock)->toBe(5)
        ->and((float) $producto->precio)->toBe(1500.0)
        ->and($producto->descripcion)->toBe('alert(1)Mesa robusta')
        ->and($producto->atributos)->toBe([['nombre' => 'Material', 'valor' => 'Pino']]);
});

it('crea productos como borrador con existencia inicial registrada como movimiento', function () {
    $admin = adminTienda();

    $this->actingAs($admin)->post('/admin/productos', [
        'nombre' => 'Banco de madera', 'modalidad_venta' => 'inventario', 'stock_inicial' => 6,
    ])->assertRedirect();

    $producto = Producto::where('nombre', 'Banco de madera')->firstOrFail();
    expect($producto->estado_publicacion)->toBe('borrador')
        ->and($producto->stock)->toBe(6)
        ->and($producto->sku)->toStartWith('ML-')
        ->and($producto->movimientosInventario()->count())->toBe(1);

    $this->actingAs($admin)->get("/admin/productos/{$producto->id}/editar")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Productos/Form')->where('producto.puede_publicarse', false));
});

it('no elimina productos con pedidos: los pausa', function () {
    $admin = adminTienda();
    $vendido = Producto::factory()->create();
    PedidoItem::factory()->create(['producto_id' => $vendido->id]);
    $nuevo = Producto::factory()->create();

    $this->actingAs($admin)->delete("/admin/productos/{$vendido->id}")->assertSessionHas('success');
    expect($vendido->fresh())->not->toBeNull()
        ->and($vendido->fresh()->estado_publicacion)->toBe(EstadoPublicacion::Pausado->value);

    $this->actingAs($admin)->delete("/admin/productos/{$nuevo->id}")->assertRedirect('/admin/productos');
    expect(Producto::find($nuevo->id))->toBeNull();
});

it('no borra categorías ni marcas con productos y permite desactivarlas', function () {
    $admin = adminTienda();
    $categoria = Categoria::factory()->create();
    $marca = Marca::factory()->create();
    Producto::factory()->create(['categoria_id' => $categoria->id, 'marca_id' => $marca->id]);

    $this->actingAs($admin)->delete("/admin/categorias/{$categoria->id}")->assertSessionHas('error');
    $this->actingAs($admin)->delete("/admin/marcas/{$marca->id}")->assertSessionHas('error');
    expect(Categoria::find($categoria->id))->not->toBeNull()
        ->and(Marca::find($marca->id))->not->toBeNull();

    $this->actingAs($admin)->patch("/admin/categorias/{$categoria->id}/estado")->assertSessionHas('success');
    expect($categoria->fresh()->activa)->toBeFalse();

    $vacia = Categoria::factory()->create();
    $this->actingAs($admin)->delete("/admin/categorias/{$vacia->id}")->assertSessionHas('success');
    expect(Categoria::find($vacia->id))->toBeNull();
});

it('valida nombre único y genera slug automático en categorías y marcas', function () {
    $admin = adminTienda();
    Categoria::factory()->create(['nombre' => 'Sala', 'slug' => 'sala']);

    $this->actingAs($admin)->post('/admin/categorias', ['nombre' => 'Sala'])->assertSessionHasErrors('nombre');
    $this->actingAs($admin)->post('/admin/categorias', ['nombre' => 'Recámara Infantil', 'activa' => true])->assertSessionHas('success');
    expect(Categoria::where('slug', 'recamara-infantil')->exists())->toBeTrue();

    $this->actingAs($admin)->post('/admin/marcas', ['nombre' => 'Mr Lana Home'])->assertSessionHas('success');
    $this->actingAs($admin)->post('/admin/marcas', ['nombre' => 'Mr Lana Home'])->assertSessionHasErrors('nombre');
    expect(Marca::where('slug', 'mr-lana-home')->exists())->toBeTrue();

    // Una categoría no puede ser hija de su propia subcategoría.
    $padre = Categoria::factory()->create();
    $hija = Categoria::factory()->create(['categoria_padre_id' => $padre->id]);
    $this->actingAs($admin)->put("/admin/categorias/{$padre->id}", ['nombre' => $padre->nombre, 'categoria_padre_id' => $hija->id])
        ->assertSessionHasErrors('categoria_padre_id');
});

it('el admin puede reimportar el catálogo desde el panel', function () {
    $this->actingAs(adminTienda())
        ->post('/admin/productos/importar-catalogo')
        ->assertRedirect('/admin/productos?estado=pendiente_aprobacion')
        ->assertSessionHas('success');

    expect(Producto::count())->toBe(25);
});
