<?php

use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstadoPagoRegistro;
use App\Enums\EstadoPublicacion;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadVenta;
use App\Enums\TipoMovimientoInventario;
use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Cupon;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\Oferta;
use App\Models\PagoComprobante;
use App\Models\Paqueteria;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\ReglaEnvio;
use App\Models\User;
use App\Services\Catalogo\Importacion\ImportadorCatalogoMuebles;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Inventario\InventarioService;
use App\Services\Pedidos\PedidoWorkflow;
use Database\Seeders\CatalogoMueblesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RutaLaravel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| Protección opcional contra errores SQL que SQLite no detecta (ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES,
| comparaciones de fechas, JSON...). Sólo corre con DB_STRICT_TEST=1 y una conexión MySQL/MariaDB:
|
|   DB_STRICT_TEST=1 DB_CONNECTION=mysql DB_DATABASE=tiendamrlana_strict DB_ENGINE=InnoDB \
|   DB_SQL_MODES=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION \
|   php artisan test --filter=BaseDatosEstricta
|
| ¡RefreshDatabase borra la base indicada! Úsala sólo con una base de pruebas desechable.
*/

beforeEach(function () {
    if (! env('DB_STRICT_TEST')) {
        $this->markTestSkipped('Define DB_STRICT_TEST=1 con una conexión MySQL/MariaDB de prueba para ejecutarla.');
    }

    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Requiere una conexión MySQL/MariaDB.');
    }
});

it('usa una sesión MySQL/MariaDB estricta', function () {
    $modo = (string) DB::selectOne('select @@session.sql_mode as m')->m;

    expect($modo)->toContain('ONLY_FULL_GROUP_BY')->toContain('STRICT_TRANS_TABLES');
});

/** Ejecuta un paso de la siembra: los errores SQL fallan la prueba; los de negocio se ignoran. */
function estrictoPaso(callable $paso): mixed
{
    try {
        return $paso();
    } catch (QueryException $e) {
        throw $e;
    } catch (Throwable) {
        return null;
    }
}

function estrictoComprobante(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('comprobante.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** Siembra datos variados: catálogo, ofertas, cupones y pedidos en todos los estados relevantes. */
function estrictoSembrar(): array
{
    sembrarMetodosPago();
    MetodoPago::query()->whereIn('clave', [MetodoPagoClave::EfectivoPresencial->value, MetodoPagoClave::EfectivoContraEntrega->value])->update(['activo' => true]);

    estrictoPaso(fn () => (new CatalogoMueblesSeeder)->run(app(ImportadorCatalogoMuebles::class)));

    $admin = adminTienda();
    $vendedor = vendedorTienda();
    $cliente = clienteTienda();
    clienteTienda();
    User::factory()->unverified()->create();

    $padre = Categoria::factory()->create(['nombre' => 'Muebles Estrictos']);
    $categoria = Categoria::factory()->create(['nombre' => 'Sillas Estrictas', 'categoria_padre_id' => $padre->id]);
    $marca = Marca::factory()->create();

    $productos = collect([
        Producto::factory()->precio(1000)->stock(50)->create(['categoria_id' => $categoria->id, 'marca_id' => $marca->id, 'atributos' => [['nombre' => 'Material', 'valor' => 'Pino']]]),
        Producto::factory()->precio(2500)->stock(30)->create(['categoria_id' => $categoria->id]),
        Producto::factory()->precio(500)->stock(2)->create(['stock_minimo' => 5, 'marca_id' => $marca->id]),
        Producto::factory()->precio(800)->bajoPedido()->create(['categoria_id' => $padre->id]),
        Producto::factory()->precio(300)->agotado()->create(),
        Producto::factory()->borrador()->create(),
        Producto::factory()->pendienteAprobacion()->create(),
        Producto::factory()->pausado()->create(),
    ]);
    [$p1, $p2, $p3] = [$productos[0], $productos[1], $productos[2]];

    estrictoPaso(fn () => app(InventarioService::class)->entrada($p1, 5, $admin, 'Entrada estricta'));

    Oferta::create(['nombre' => '10% sillas', 'tipo' => 'porcentaje', 'valor' => 10, 'aplica_a' => 'categoria', 'categoria_id' => $categoria->id, 'activa' => true]);
    Oferta::create(['nombre' => 'Marca', 'tipo' => 'monto_fijo', 'valor' => 50, 'aplica_a' => 'marca', 'marca_id' => $marca->id, 'activa' => true, 'inicia_en' => now()->subDay(), 'termina_en' => now()->addWeek()]);
    Oferta::create(['nombre' => 'Vencida', 'tipo' => 'porcentaje', 'valor' => 5, 'aplica_a' => 'categoria', 'categoria_id' => $categoria->id, 'activa' => true, 'inicia_en' => now()->subMonth(), 'termina_en' => now()->subWeek()]);
    estrictoPaso(function () use ($p2) {
        $o = Oferta::create(['nombre' => 'Producto', 'tipo' => 'porcentaje', 'valor' => 15, 'aplica_a' => 'productos', 'activa' => false]);
        $o->productos()->attach($p2->id);
    });

    Cupon::factory()->create(['codigo' => 'ESTRICTO10']);
    Cupon::factory()->montoFijo(100)->create();
    Cupon::factory()->vencido()->create();
    Cupon::factory()->agotado()->create();

    Banner::query()->create(['titulo' => 'Vigente', 'imagen' => 'banners/a.jpg', 'activo' => true]);
    Banner::query()->create(['titulo' => 'Vencido', 'imagen' => 'banners/b.jpg', 'activo' => true, 'termina_en' => now()->subDay()]);

    $estafeta = Paqueteria::factory()->conRastreo()->create(['nombre' => 'Estafeta']);
    Paqueteria::factory()->create(['nombre' => 'DHL']);
    Paqueteria::factory()->inactiva()->create();
    ReglaEnvio::factory()->recoger()->create();
    ReglaEnvio::factory()->cotizacionManual()->create(['activo' => false]);

    $config = app(ConfiguracionTienda::class);
    foreach (['envios', 'devoluciones', 'privacidad', 'terminos'] as $politica) {
        $config->set('politicas.'.$politica, 'Contenido de '.$politica.' para la prueba estricta.');
    }
    $config->set('home.faq', [['pregunta' => '¿Envían?', 'respuesta' => 'Sí, a todo México.']]);
    $config->set('banco.nombre', 'Banco Prueba');
    $config->set('banco.beneficiario', 'Mr Lana SA de CV');
    $config->set('banco.clabe', '002010077777777771');

    $wf = app(PedidoWorkflow::class);
    $pedido = fn (string $metodo = 'transferencia', string $modalidad = 'envio_domicilio', ?string $cupon = null, ?Producto $p = null) => estrictoPaso(
        fn () => crearPedidoCheckout($cliente, [[$p ?? $p1, 1], [$p2, 1]], $metodo, $modalidad, cupon: $cupon)
    );
    $opciones = fn () => [
        ['paqueteria_id' => $estafeta->id, 'servicio' => 'Estándar', 'costo' => 180, 'dias_min' => 4, 'dias_max' => 6],
        ['paqueteria_id' => $estafeta->id, 'servicio' => 'Express', 'costo' => 420.5, 'dias_min' => 1, 'dias_max' => 2, 'recomendada' => true],
    ];
    $conEnvio = function (?Pedido $p) use ($wf, $admin, $cliente, $opciones) {
        if (! $p) {
            return null;
        }
        estrictoPaso(fn () => $wf->enviarOpcionesEnvio($p->fresh(), $admin, $opciones()));
        estrictoPaso(fn () => $wf->elegirOpcionEnvio($p->fresh(), $cliente, $p->opcionesEnvio()->firstOrFail()->id));

        return $p->fresh();
    };
    $pagado = function (?Pedido $p) use ($wf, $admin, $cliente, $conEnvio) {
        $p = $conEnvio($p);
        if (! $p) {
            return null;
        }
        estrictoPaso(fn () => $wf->registrarComprobante($p, $cliente, estrictoComprobante(), 'REF-'.$p->id, now()->toDateString(), (float) $p->fresh()->total));
        estrictoPaso(fn () => $wf->aprobarPago($p->pagos()->latest('id')->firstOrFail(), $admin));

        return $p->fresh();
    };

    // Por cotizar / con opciones / pendiente de pago.
    $pedido();
    estrictoPaso(fn () => $wf->enviarOpcionesEnvio($pedido()->fresh(), $admin, $opciones()));
    $conEnvio($pedido(cupon: 'ESTRICTO10'));
    // En revisión y rechazado.
    $revision = $conEnvio($pedido());
    estrictoPaso(fn () => $wf->registrarComprobante($revision, $cliente, estrictoComprobante(), 'REF-REV', now()->toDateString(), (float) $revision->total));
    $rechazado = $conEnvio($pedido());
    estrictoPaso(fn () => $wf->registrarComprobante($rechazado, $cliente, estrictoComprobante(), 'REF-RECH', now()->toDateString(), 1));
    estrictoPaso(fn () => $wf->rechazarPago($rechazado->pagos()->latest('id')->firstOrFail(), $admin, 'Monto incorrecto'));
    // Confirmado, en preparación, listo, enviado, entregado, incidencia, reembolsado.
    $pagado($pedido());
    $prep = $pagado($pedido());
    estrictoPaso(fn () => $wf->iniciarPreparacion($prep, $admin));
    $listo = $pagado($pedido());
    estrictoPaso(fn () => $wf->iniciarPreparacion($listo, $admin));
    estrictoPaso(fn () => $wf->marcarListoEnvio($listo->fresh(), $admin));
    foreach (['enviado', 'entregado', 'incidencia'] as $fin) {
        $p = $pagado($pedido());
        estrictoPaso(fn () => $wf->iniciarPreparacion($p, $admin));
        estrictoPaso(fn () => $wf->marcarListoEnvio($p->fresh(), $admin));
        estrictoPaso(fn () => $wf->registrarEnvio($p->fresh(), $admin, 'Estafeta', 'GUIA'.$p->id, now()->toDateString()));
        if ($fin === 'entregado') {
            estrictoPaso(fn () => $wf->confirmarEntrega($p->fresh(), $admin));
        }
        if ($fin === 'incidencia') {
            estrictoPaso(fn () => $wf->registrarIncidencia($p->fresh(), $admin, 'Domicilio no localizado'));
        }
    }
    $reemb = $pagado($pedido());
    estrictoPaso(fn () => $wf->cancelar($reemb, $admin, 'Cliente desistió'));
    estrictoPaso(fn () => $wf->registrarReembolso($reemb->fresh(), $admin, 'Reembolso total'));
    // Cancelado, vencido, nota interna.
    $cancelado = $pedido();
    estrictoPaso(fn () => $wf->cancelar($cancelado, $admin, 'Cancelado por prueba'));
    $vencido = $conEnvio($pedido());
    estrictoPaso(function () use ($wf, $vencido) {
        $vencido->forceFill(['vence_en' => now()->subDay()])->save();
        $wf->vencer($vencido->fresh());
    });
    estrictoPaso(fn () => $wf->agregarNota($cancelado->fresh(), $vendedor, 'Nota interna', true));
    // Efectivo: presencial (recoger) y contra entrega.
    $recoger = $pedido('efectivo_presencial', 'recoger_en_tienda');
    estrictoPaso(fn () => $wf->marcarListoRecoger($recoger, $admin));
    $conEnvio($pedido('efectivo_contra_entrega'));
    // Pedido histórico de otro cliente (vía factory), con fechas antiguas.
    Pedido::factory()->aprobado()->create(['created_at' => now()->subMonths(2), 'estado_logistico' => EstadoLogistico::Entregado->value, 'estatus' => EstatusPedido::Entregado->value]);
    Pedido::factory()->create(['estatus' => EstatusPedido::PendientePago->value, 'estado_pago' => EstadoPago::Pendiente->value, 'metodo_pago' => 'openpay']);

    return compact('admin', 'vendedor', 'cliente');
}

/** @return array<string, array<int, string>> Valores candidatos por parámetro de ruta. */
function estrictoParametros(User $cliente): array
{
    return [
        'pedido' => Pedido::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'producto' => Producto::query()->orderByDesc('id')->limit(12)->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'producto:slug' => [...Producto::query()->publicados()->orderBy('id')->limit(8)->pluck('slug')->all(), 'no-existe'],
        'categoria' => Categoria::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'marca' => Marca::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'comprobante' => PagoComprobante::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
        'slug' => ['envios', 'devoluciones', 'privacidad', 'terminos', 'no-existe'],
        'token' => ['token-de-prueba'],
    ];
}

/** @return array<string, array<int, array<string, string>>> Query strings por nombre de ruta. */
function estrictoFiltros(): array
{
    $cada = fn (string $clave, array $valores) => array_map(fn ($v) => [$clave => (string) $v], $valores);
    $fechas = [['desde' => now()->subMonth()->toDateString(), 'hasta' => now()->toDateString()], ['desde' => now()->toDateString()], ['hasta' => now()->toDateString()]];
    $primerProducto = (string) Producto::query()->value('id');
    $folio = (string) Pedido::query()->value('folio');
    $categoria = (string) Categoria::query()->value('slug');
    $marca = (string) Marca::query()->value('slug');

    return [
        'dashboard' => $cada('periodo', ['hoy', '7d', '30d', 'mes', 'otro']),
        'admin.pedidos.index' => [
            ...$cada('estatus', EstatusPedido::values()),
            ...$cada('pago', EstadoPago::values()),
            ...$cada('logistico', EstadoLogistico::values()),
            ...$cada('metodo', MetodoPagoClave::values()),
            ['buscar' => 'ML'], ['buscar' => $folio], ['buscar' => "100%_'x"],
            ['estatus' => 'confirmado', 'pago' => 'aprobado', 'logistico' => 'pendiente', 'metodo' => 'transferencia', 'buscar' => 'a', 'page' => '2'],
        ],
        'admin.productos.index' => [
            ...$cada('estado', EstadoPublicacion::values()),
            ...$cada('orden', ['recientes', 'nombre', 'precio', 'stock']),
            ...$cada('stock', ['agotado', 'bajo_minimo']),
            ...$cada('modalidad', ModalidadVenta::values()),
            ['q' => 'silla'], ['categoria_id' => (string) Categoria::query()->value('id')], ['marca_id' => (string) Marca::query()->value('id')],
            ['q' => 'a', 'estado' => 'publicado', 'stock' => 'bajo_minimo', 'orden' => 'precio', 'page' => '2'],
        ],
        'admin.inventario.index' => [
            ...$cada('tipo', TipoMovimientoInventario::values()),
            ...$fechas,
            ['producto_id' => $primerProducto, 'tipo' => 'venta', ...$fechas[0]],
        ],
        'admin.pagos.index' => [
            ...$cada('tab', ['revision', 'efectivo', 'historial']),
            ...array_map(fn ($e) => ['tab' => 'historial', 'estatus' => $e], EstadoPagoRegistro::values()),
            ...array_map(fn ($t) => ['tab' => 'historial', 'tipo' => $t], MetodoPagoClave::values()),
            ['buscar' => 'REF'], ['tab' => 'historial', 'buscar' => $folio, 'pago' => '1'],
        ],
        'admin.envios.index' => [...$cada('tab', ['por_cotizar', 'esperando_eleccion', 'por_preparar', 'en_preparacion', 'listos', 'enviados']), ['buscar' => 'ML']],
        'admin.usuarios.index' => [...$cada('rol', ['admin', 'vendedor', 'cliente']), ['buscar' => 'a']],
        'admin.cupones.index' => [...$cada('estado', ['activos', 'inactivos', 'vencidos']), ['buscar' => 'EST']],
        'admin.ofertas.index' => [...$cada('estado', ['vigentes', 'inactivas', 'vencidas']), ['buscar' => 'a']],
        'admin.categorias.index' => [...$cada('estado', ['activas', 'inactivas']), ['q' => 'silla']],
        'admin.marcas.index' => [...$cada('estado', ['activas', 'inactivas']), ['q' => 'a']],
        'cliente.pedidos.index' => $cada('estado', ['por_pagar', 'en_proceso', 'entregados', 'cerrados']),
        'tienda.catalogo' => [
            ...$cada('orden', ['relevancia', 'precio_asc', 'precio_desc', 'nuevos', 'nombre']),
            ...$cada('disponibilidad', ['en_stock', 'bajo_pedido', 'agotado']),
            ['q' => 'silla'], ['q' => 'silla mesa', 'orden' => 'relevancia'], ['categoria' => $categoria], ['marca' => $marca],
            ['precio_min' => '100', 'precio_max' => '2000'], ['buscar' => 'mueble', 'page' => '2'],
        ],
        'tienda.sugerencias' => [['q' => 'si'], ['q' => 'mesa de']],
    ];
}

it('recorre todas las rutas GET con cada rol sin errores 500', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    $usuarios = estrictoSembrar();
    $parametros = estrictoParametros($usuarios['cliente']);
    $filtros = estrictoFiltros();
    $omitir = ['storage/{path}', 'email/verify/{id}/{hash}'];

    $rutas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RutaLaravel $r) => in_array('GET', $r->methods(), true))
        ->reject(fn (RutaLaravel $r) => in_array($r->uri(), $omitir, true) || str_starts_with($r->uri(), '_'));

    $errores = [];
    $peticiones = 0;

    foreach (['invitado' => null, ...$usuarios] as $rol => $usuario) {
        foreach ($rutas as $ruta) {
            $uris = [$ruta->uri()];
            foreach ($ruta->parameterNames() as $nombre) {
                $clave = ($ruta->bindingFieldFor($nombre) ? $nombre.':'.$ruta->bindingFieldFor($nombre) : $nombre);
                $valores = $parametros[$clave] ?? $parametros[$nombre] ?? ['1'];
                $uris = collect($uris)->flatMap(fn ($u) => collect($valores)->map(fn ($v) => preg_replace('/\{'.preg_quote($nombre, '/').'(:[^}]*)?\??\}/', $v, $u)))->all();
            }

            $consultas = [[], ...($filtros[$ruta->getName() ?? ''] ?? [])];

            foreach ($uris as $uri) {
                foreach ($consultas as $consulta) {
                    $url = '/'.ltrim($uri, '/').($consulta ? '?'.http_build_query($consulta) : '');
                    $prueba = $usuario ? $this->actingAs($usuario) : $this;
                    $respuesta = $prueba->withSession(['auth.password_confirmed_at' => time()])->get($url);
                    $peticiones++;
                    $codigos[$respuesta->getStatusCode()] = ($codigos[$respuesta->getStatusCode()] ?? 0) + 1;
                    $sinExito[$rol.' '.$ruta->uri()] ??= true;
                    if ($respuesta->getStatusCode() < 400) {
                        $sinExito[$rol.' '.$ruta->uri()] = false;
                    }

                    if ($respuesta->getStatusCode() >= 500) {
                        $e = $respuesta->exception;
                        $errores[] = sprintf('[%s] GET %s -> %d %s', $rol, $url, $respuesta->getStatusCode(), $e ? class_basename($e).': '.mb_substr($e->getMessage(), 0, 400) : '');
                    }
                }
            }

            if ($usuario) {
                auth()->logout();
            }
        }
    }

    if (env('DB_STRICT_DEBUG')) {
        fwrite(STDERR, json_encode(['peticiones' => $peticiones, 'codigos' => $codigos ?? [], 'estatus' => Pedido::query()->toBase()->select('estatus')->selectRaw('count(*) as total')->groupBy('estatus')->pluck('total', 'estatus'), 'productos' => Producto::count(), 'comprobantes' => PagoComprobante::count(), 'sin_exito' => array_keys(array_filter($sinExito ?? []))]).PHP_EOL);
    }

    expect($peticiones)->toBeGreaterThan(100)
        ->and(Pedido::query()->distinct()->count('estatus'))->toBeGreaterThan(5)
        ->and($errores)->toBe([]);
})->skip(fn () => ! env('DB_STRICT_TEST'), 'Define DB_STRICT_TEST=1 para ejecutarla.');

it('acepta los textos más largos que permite la validación sin truncar en modo estricto', function () {
    Storage::fake('public');
    $admin = adminTienda();
    $cliente = clienteTienda();
    $correoLargo = fn (string $p) => $p.str_repeat('a', 64 - strlen($p)).'@'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.'.str_repeat('d', 60).'.mx'; // 250 caracteres.
    $max = fn (int $n) => str_repeat('ñ', $n);

    $casos = [
        'registro' => fn () => $this->post(route('register.store'), ['name' => $max(255), 'email' => $correoLargo('r'), 'password' => 'Secreta-123456', 'password_confirmation' => 'Secreta-123456']),
        'perfil' => fn () => $this->actingAs($cliente)->patch(route('profile.update'), ['name' => $max(255), 'email' => $correoLargo('p')]),
        'usuarios.store' => fn () => $this->actingAs($admin)->post(route('admin.usuarios.store'), ['name' => $max(255), 'email' => $correoLargo('u'), 'rol' => 'vendedor']),
        'cupones.store' => fn () => $this->actingAs($admin)->post(route('admin.cupones.store'), ['codigo' => 'LARGO1', 'nombre' => $max(160), 'tipo' => 'porcentaje', 'valor' => 10, 'activo' => true]),
        'banners.store' => fn () => $this->actingAs($admin)->post(route('admin.banners.store'), ['titulo' => $max(180), 'url' => '/'.str_repeat('a', 254), 'activo' => true, 'imagen_file' => UploadedFile::fake()->image('b.jpg', 800, 300)]),
        'direcciones.store' => fn () => $this->actingAs($cliente)->post(route('cliente.direcciones.store'), [
            'alias' => $max(100), 'nombre_receptor' => $max(180), 'telefono' => str_repeat('5', 30), 'calle' => $max(180), 'numero_exterior' => $max(50),
            'numero_interior' => $max(50), 'colonia' => $max(150), 'municipio' => $max(150), 'estado' => $max(150), 'pais' => $max(150), 'codigo_postal' => str_repeat('1', 20),
        ]),
    ];

    $errores = [];
    foreach ($casos as $nombre => $caso) {
        $respuesta = $caso();
        if (env('DB_STRICT_DEBUG')) {
            fwrite(STDERR, $nombre.' '.$respuesta->getStatusCode().' '.json_encode(session('errors')).PHP_EOL);
        }
        if ($respuesta->getStatusCode() >= 500) {
            $errores[] = $nombre.': '.mb_substr((string) $respuesta->exception?->getMessage(), 0, 200);
        }
        auth()->logout();
    }

    expect($errores)->toBe([]);
});
