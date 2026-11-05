# Tienda Mr Lana — Contrato de arquitectura (fase de finalización)

Documento vivo y fuente de verdad compartida por todos los equipos de trabajo. Si algo
aquí cambia, se actualiza este archivo. Idioma de código de dominio: español
(como el proyecto existente). Commits en español.

## 0. Reglas no negociables

- **Producción ya tiene datos**: NUNCA editar migraciones existentes. Todo cambio de
  esquema va en migraciones nuevas, aditivas y reversibles (`down()` real). Mapear datos
  viejos dentro de la migración cuando cambien significados de columnas.
- Pruebas en SQLite `:memory:` (phpunit.xml). Las migraciones deben funcionar en SQLite y
  MariaDB 11.4. Nada de SQL crudo específico de MySQL sin rama para sqlite.
- **Sin tarjetas**: ningún flujo público puede capturar tarjeta ni cobrar. Openpay queda
  deshabilitado por `config('payments.enabled')` = `false` (env `PAYMENTS_ONLINE_ENABLED`),
  y `PaymentService::charge()` lanza `OnlinePaymentsDisabledException` si está deshabilitado.
  La ruta `checkout.pagar` se elimina.
- **No inventar datos comerciales**: banco, CLABE, beneficiario, teléfonos, direcciones,
  costos de envío, precios, existencias. Todo sale de configuración administrable; si falta,
  la UI muestra el estado "pendiente de configurar" (al admin) o se oculta (al público).
- El backend recalcula todos los importes. El cliente jamás envía precios/totales.
- Montos: `decimal(12,2)`; en PHP trabajar con `round(..., 2)`.
- Fechas a mostrar en zona `America/Mexico_City` (config `app.timezone` se deja en UTC si
  ya está; formatear en front con `Intl` `es-MX`).

## 1. Roles y permisos

Roles en `users.rol`: `admin`, `vendedor`, `cliente` (constantes en `User`).
Gates en `AppServiceProvider` (o `AuthServiceProvider` equivalente):

| Gate | Quién |
|---|---|
| `gestionar-catalogo` | admin |
| `gestionar-configuracion` | admin |
| `gestionar-usuarios` | admin |
| `revisar-pagos` (aprobar/rechazar transferencias, confirmar efectivo, reembolsos) | admin |
| `operar-pedidos` (preparar, enviar, guía, entrega, incidencias, notas) | admin, vendedor |
| `cotizar-envio` | admin, vendedor |
| `cancelar-pedidos` | admin |

Rutas admin con `middleware('role:admin')` o `can:...` según la tabla. El vendedor usa el
mismo panel `/admin/pedidos` con acciones limitadas por gate (el front recibe
`can: { revisar_pagos, operar_pedidos, ... }` en props compartidas `auth.can`).

## 2. Esquema nuevo (migraciones aditivas)

### productos (alter)
- `estado_publicacion` string(30) default `borrador` — `borrador | pendiente_aprobacion | publicado | pausado`.
  Migración de datos: filas existentes con `activo && visible` → `publicado`, resto → `pausado`.
- `modalidad_venta` string(20) default `inventario` — `inventario | bajo_pedido`.
- `tiempo_entrega_dias` unsignedSmallInteger nullable (para bajo pedido).
- `precio` pasa a **nullable** (borradores sin precio). Un producto sólo puede publicarse con precio > 0.
- `precio_referencia` decimal(12,2) nullable, `fuente_referencia` string(255) nullable,
  `referencia_consultada_en` date nullable — datos de referencia NO comerciales (no se muestran al público).
- `codigo_referencia` string(60) nullable, index (código del modelo de referencia del catálogo).
- `modelo` string(120) nullable, `acabado` string(180) nullable, `dimensiones` string(180) nullable,
  `observaciones` text nullable, `atributos` json nullable (lista `{nombre, valor}`).
- `meta_titulo` string(180) nullable, `meta_descripcion` string(300) nullable.
- `aprobado_por` FK users nullable, `aprobado_en` timestamp nullable.
- `importacion_origen` string(80) nullable (p.ej. `catalogo_muebles_mx_2026`), `importacion_clave` string(120) nullable unique — llave idempotente de importación.

Scopes en `Producto`: `publicados()` (estado_publicacion=publicado AND activo AND visible AND precio not null),
`vendibles()` (publicados y (bajo_pedido o stock>0)). Helper `estaDisponible(int $cantidad)`.
Regla de publicación (`Producto::faltantesParaPublicar(): array`): precio>0, categoría, al menos 1 imagen
autorizada (imagen_principal o imagenes), y para `inventario` stock definido (puede ser 0 → se ve "Agotado").

### inventario_movimientos (nueva)
`id, producto_id FK cascade, user_id FK null, tipo string(30) (entrada|salida|ajuste|venta|liberacion|devolucion),
cantidad int (con signo), stock_anterior int, stock_nuevo int, pedido_id FK null, motivo string(255) null, timestamps`.
Servicio `App\Services\Inventario\InventarioService` es el ÚNICO que modifica `productos.stock`
(con `lockForUpdate`).

### pedidos (alter)
- `estado_pago` string(40) default `pendiente`:
  `pendiente | en_revision | aprobado | rechazado | contra_entrega | reembolsado | no_aplica`.
- `estado_logistico` string(40) default `pendiente`:
  `pendiente | en_preparacion | listo_envio | enviado | entregado | listo_recoger | incidencia | devuelto`.
- `estatus` (existente) = estado GENERAL derivado, valores:
  `pendiente_cotizacion | eleccion_envio | pendiente_pago | pago_en_revision | confirmado | en_preparacion | listo_envio | enviado | entregado | cancelado | vencido | reembolsado`
  (`eleccion_envio` = el cliente debe elegir una opción de envío; ver §13).
  Migración de datos: `pendiente→pendiente_pago`, `pagado→confirmado` (estado_pago=aprobado),
  `preparando→en_preparacion`, `enviado→enviado`, `entregado→entregado`, `cancelado→cancelado`, `reembolsado→reembolsado`.
- `metodo_pago` string(40) nullable — `transferencia | efectivo_presencial | efectivo_contra_entrega` (históricos: `openpay`).
- `modalidad_entrega` string(30) default `envio_domicilio` — `envio_domicilio | recoger_en_tienda`.
- `regla_envio_id` FK null, `envio_estado` string(20) default `calculado` — `calculado | por_cotizar | opciones_enviadas | cotizado`,
  `envio_cotizado_por` FK users null, `envio_cotizado_en` timestamp null.
  `por_cotizar` y `opciones_enviadas` = total NO final (`EstadoEnvio::pendientes()`, `Pedido::envioPendiente()`).
- `direccion_envio` json null (migración `2026_11_05_001000`) — copia de la dirección al comprar
  (`Direccion::copiaParaPedido()`: id, alias, nombre_receptor, telefono, calle, numero_exterior, numero_interior,
  colonia, municipio, estado, pais, codigo_postal, referencias). Rellenada para pedidos existentes.
  Presentadores (cliente, admin, PDF, correos) usan `Pedido::direccionEntrega()` (copia → relación como respaldo).
- `saldo_pendiente` decimal(12,2) default 0.
- `vence_en` timestamp null (sólo transferencia/efectivo presencial sin pago).
- `listo_envio_en`, `vencido_en` timestamps null; `fecha_envio` date null.
- `cancelado_por` FK users null, `motivo_cancelacion` string(500) null.
- `cupon_id` FK null, `cupon_codigo` string(60) null.
- `stock_reservado` boolean default false.

### pagos (alter)
- `tipo` string(40) null — `transferencia | efectivo_presencial | efectivo_contra_entrega | openpay`.
- `estatus` valores nuevos: `pendiente | en_revision | aprobado | rechazado | cancelado | reembolsado`
  (históricos openpay se conservan tal cual).
- `referencia_cliente` string(120) null, `revisado_por` FK null, `revisado_en` ts null,
  `motivo_rechazo` string(500) null, `notas_internas` text null,
  `monto_recibido` decimal(12,2) null, `recibido_por` FK null, `recibido_en` ts null.

### pago_comprobantes (nueva)
`id, pago_id FK cascade, user_id FK null, disk string(30) default 'local', ruta string(255),
nombre_original string(255), mime string(100), tamano unsignedInteger, sha256 char(64),
referencia string(120) null, fecha_transferencia date null, monto_declarado decimal(12,2) null,
estado string(20) default 'recibido' (recibido|aprobado|rechazado), timestamps`.
Archivos en disco **privado** `local` (storage/app/private/comprobantes/{pedido_id}/{uuid}.ext).
Tipos permitidos: jpg, jpeg, png, webp, pdf; máx 5 MB; validar mime real.
Descarga sólo vía ruta controlada con policy (dueño del pedido o `revisar-pagos`/`operar-pedidos`).

### pedido_estatus_historial (alter)
- `tipo` string(20) default `general` — `general | pago | logistico | envio | nota | sistema`.
- `estado_anterior` string(40) null, `datos` json null, `visible_cliente` boolean default true.

### reglas_envio (nueva)
`id, nombre string(120), tipo string(30) (tarifa_fija | cotizacion_manual | recoger_en_tienda),
estados json null (nombres de estado MX, null=todos), cp_prefijos json null, monto decimal(12,2) null,
gratis_desde decimal(12,2) null (sólo si el negocio lo define), dias_estimados string(60) null,
permite_contra_entrega bool default false, activo bool default true, prioridad int default 0, notas text null, timestamps`.
Sin reglas configuradas → todo pedido a domicilio queda `por_cotizar`. Nunca "Envío gratis" sin regla `gratis_desde`.

### paqueterias (nueva, migración `2026_11_05_000900`)
`id, nombre string(80) unique, activo bool default true, url_rastreo string(500) null (debe contener {guia}),
notas text null, timestamps`. Sin datos precargados: el admin las da de alta en `/admin/paqueterias`.

### pedido_opciones_envio (nueva, migración `2026_11_05_000900`)
`id, pedido_id FK cascade, paqueteria_id FK null (nullOnDelete), paqueteria_nombre string(80) (copia),
servicio string(120), costo decimal(12,2), dias_min/dias_max unsignedSmallInteger null, tiempo_texto string(120) null,
notas string(500) null (visibles al cliente), recomendada bool, seleccionada_en ts null, creado_por FK users null, timestamps`.

### notificaciones_pedido (nueva) — deduplicación de correos
`id, pedido_id FK cascade, tipo string(60), clave string(120) unique, enviado_en ts, timestamps`.

### metodos_pagos (datos)
Seeder idempotente `MetodosPagoSeeder` (updateOrCreate por `clave`) — se ejecuta en producción:
- `transferencia` activo=true, `efectivo_presencial` activo=true, `efectivo_contra_entrega` activo=false,
  `openpay` activo=false + `configuracion.bloqueado=true` (no se puede activar desde el panel mientras
  `payments.enabled=false`).
`configuracion` JSON por método: `instrucciones`, `horas_vencimiento` (transferencia, default 48),
contra entrega: `estados` permitidos / `monto_maximo`.

### configuraciones (claves)
Servicio `App\Services\Configuracion\ConfiguracionTienda` (cache, getters tipados, `set()`), claves:
`tienda.nombre, tienda.eslogan, contacto.telefono, contacto.whatsapp, contacto.correo, contacto.direccion,
contacto.horario, banco.nombre, banco.beneficiario, banco.clabe, banco.cuenta, banco.referencia_instrucciones,
recoger.direccion, recoger.horario, politicas.envios, politicas.devoluciones, politicas.privacidad,
politicas.terminos, home.faq (json [{pregunta,respuesta}]), home.beneficios (json [{titulo,descripcion,icono}]),
redes.facebook, redes.instagram, redes.tiktok, seo.descripcion`.
`banco.*` se expone sólo a: página de instrucciones del pedido del dueño, correo de instrucciones
(sin datos extra), y panel admin. CLABE validada (18 dígitos + dígito verificador).

## 3. Flujo de estados (servicio `App\Services\Pedidos\PedidoWorkflow`)

Todas las transiciones: `DB::transaction` + `Pedido::lockForUpdate()`, guardas explícitas
(lanzan `TransicionInvalidaException` con mensaje legible), historial, y notificación
**después del commit** (`DB::afterCommit` / `ShouldQueueAfterCommit`).

Métodos (firma orientativa):
- `crearDesdeCheckout(User, DatosCheckout): Pedido` — recalcula, valida stock con lock, reserva stock
  (InventarioService::reservarParaPedido → movimientos tipo `venta`), registra uso de cupón, crea Pago pendiente,
  fija `vence_en`, `estatus` = `pendiente_cotizacion` si envío por cotizar, si no `pendiente_pago`
  (contra entrega → `confirmado` con `estado_pago=contra_entrega`, `saldo_pendiente=total`).
- `cotizarEnvio(Pedido, monto, User, nota)` — costo directo; sólo si el envío está pendiente (`por_cotizar|opciones_enviadas`,
  descarta opciones no elegidas); recalcula total, pasa a `pendiente_pago`.
- `enviarOpcionesEnvio(Pedido, User, opciones[], ?motivo)` / `elegirOpcionEnvio(Pedido, User, opcionId)` /
  `puedeRecotizar(Pedido)` — cotización con varias paqueterías (§13).
- `registrarComprobante(Pedido, User, UploadedFile, referencia, fecha, monto)` — dueño; estado_pago pendiente|rechazado → `en_revision`; estatus `pago_en_revision`.
- `aprobarPago(Pago, User, notas)` — gate revisar-pagos; sólo desde `en_revision` (transferencia) o `pendiente` (efectivo presencial); idempotente vía lock: si ya aprobado → excepción "ya fue aprobado". Registra revisado_por/en, `pagado_en`, `saldo_pendiente=0`, estatus `confirmado`.
- `rechazarPago(Pago, User, motivo)` — motivo obligatorio; estado_pago `rechazado`, estatus `pendiente_pago` (cliente puede re-subir).
- `confirmarEfectivo(Pago, User, montoRecibido, notas)` — presencial o contra entrega; monto >= saldo.
- `iniciarPreparacion`, `marcarListoEnvio`, `registrarEnvio(paqueteria, guia, fecha_envio)`,
  `confirmarEntrega`, `marcarListoRecoger`, `registrarIncidencia(nota)`.
  Guarda de preparación: `estado_pago=aprobado` o (`metodo_pago=efectivo_contra_entrega` y método/regla lo permiten).
  Entrega con contra entrega exige confirmar efectivo antes o en la misma acción.
- `cancelar(Pedido, User, motivo)` — libera stock (movimientos `liberacion`), no si ya enviado/entregado.
  Si había pago aprobado → estado_pago se mantiene y se marca "reembolso manual pendiente" en historial.
- `registrarReembolso(Pedido, User, nota)` — estado_pago `reembolsado`, estatus `reembolsado`.
- `vencer(Pedido)` — comando programado `pedidos:vencer` (cada 15 min en scheduler): pedidos `pendiente_pago`
  con `vence_en < now()` y sin comprobante → `vencido`, libera stock.
- `agregarNota(Pedido, User, nota, visibleCliente=false)`.

`estatus` general se recalcula en un solo método `Pedido::recalcularEstatus()`.

## 4. Precios, carrito y checkout (servicios)

- `App\Services\Catalogo\PrecioService` — oferta vigente (producto > categoría > marca, mayor descuento),
  devuelve `{precio_original, precio_final, oferta}`. Reemplaza la lógica duplicada de controladores.
- `App\Services\Carrito\CarritoService` — carrito en sesión (`carrito` = [{producto_id, cantidad}] SOLO ids y cantidades);
  `resumen()` recalcula todo desde BD; valida stock/bajo pedido; máx 99 por línea.
- `App\Services\Cupones\CuponService::validar(codigo, subtotal, ?User)` — activo, vigencia (inicia/termina),
  usos máximos globales y por usuario, mínimo de compra; mensajes claros. Registro en `cupones_usos` al crear pedido.
- `App\Services\Envios\EnvioService::cotizar(direccion|modalidad, subtotal, items)` →
  `{modalidad, regla_id, estado: calculado|por_cotizar, monto|null, permite_contra_entrega, dias_estimados}`.
- Props compartidas Inertia: `carrito.cantidad_items` calculado desde CarritoService (sin precios de sesión).

## 5. Rutas (archivos)

`routes/web.php` sólo incluye:
- `routes/tienda.php` — home, catálogo, producto, páginas de políticas/FAQ, `sitemap.xml`, manifest PWA si es dinámico.
- `routes/compra.php` — carrito, cupón, checkout, gracias/instrucciones, subir comprobante, mis pedidos, PDF de pedido, descarga de comprobante propio, direcciones.
- `routes/admin-operacion.php` — dashboard admin, pedidos (acciones del workflow), pagos, comprobantes (descarga), envíos, reglas de envío, configuración (negocio, banco, métodos de pago, políticas, home), usuarios, banners, ofertas, cupones.
- `routes/admin-catalogo.php` — productos, imágenes, inventario/movimientos, categorías, marcas, importación de catálogo y cola de aprobación.
Cada archivo lo mantiene un solo equipo. Se conservan nombres de rutas existentes cuando sigan aplicando (Wayfinder genera `resources/js/routes` y `resources/js/actions` — no editar a mano, regenerar con `php artisan wayfinder:generate`).

## 6. PDF (Browsershot)

`spatie/browsershot` + `puppeteer` (npm). Servicio `App\Services\Pdf\PdfService::desdeVista(view, data): string`
con config `config/pdf.php` (`node_binary`, `npm_binary`, `chrome_path`, `no_sandbox`) desde env
(`BROWSERSHOT_NODE_BINARY`, `BROWSERSHOT_NPM_BINARY`, `BROWSERSHOT_CHROME_PATH`, `BROWSERSHOT_NO_SANDBOX`).
Documentos: "Resumen de pedido" (cliente y admin, con instrucciones de pago si aplica) y "Hoja de empaque"
(admin). En pruebas se hace fake del servicio. Si Chrome no está disponible → error controlado y
mensaje, nunca 500 crudo.

## 7. Identidad visual

Manual en `docs/identidad`. Paleta:
- Verde Mr Lana `#73C83A` (marca/primario), Turquesa `#64CBD4` (acento), Negro `#0A0A0A` (texto), Blanco.
- Degradado de marca: `linear-gradient(135deg, #73C83A 0%, #64CBD4 100%)`.
- Verde profundo para fondos oscuros: `#1F4D3A` → `#14332B` (como portadas del manual).
- Gris cálido de fondo del manual `#E3E1DE` (usar como superficie suave).
- Tipografía: títulos **Baloo 2** (redondeada, cercana a Museo Sans Rounded), texto **Montserrat**. Google Fonts.
Tokens definidos en `resources/css/app.css` (`--primary`, `--accent`, `--brand-green`, `--brand-teal`,
`--brand-deep`, utilidades `bg-brand-gradient`, `text-brand-gradient`, `font-display`). Componentes shadcn-vue
(`resources/js/components/ui`). Sin calendarios ni gráficas: indicadores dinámicos con tarjetas, contadores
animados, barras de progreso de estado y líneas de tiempo.
Texto con contraste AA: el verde de marca sobre blanco NO alcanza AA para texto pequeño → para texto/links usar
`--brand-green-strong` (#3F8A1C) y reservar `#73C83A` para fondos, iconos, botones con texto negro.

**Tokens ya disponibles en `app.css` (equipo tienda, no renombrar):** variables `--brand-green`, `--brand-green-strong`,
`--brand-green-soft`, `--brand-teal`, `--brand-teal-strong`, `--brand-teal-soft`, `--brand-deep`, `--brand-deep-2`,
`--brand-warm`, `--brand-ink`, `--brand-gradient`, `--surface`, `--success`, `--warning`, `--info` (+ `-foreground`).
Clases Tailwind: `bg-brand-green`, `text-brand-green-strong`, `bg-brand-teal-soft`, `bg-brand-deep`, `bg-surface`,
`bg-success`/`text-warning`/`bg-info`… ; utilidades `bg-brand-gradient`, `bg-brand-deep-gradient`, `text-brand-gradient`,
`font-display` (Baloo 2; `font-sans` = Montserrat), `bg-brand-pattern` (rombos sobre oscuro), `bg-brand-pattern-light`, `bg-brand-deep-pattern` (verde profundo + rombos),
`animate-marquee`, `animate-float`, `.reveal`/`.is-visible`. `--primary` = verde marca con texto negro (AA en claro y oscuro).

## 8. PWA

`public/manifest.webmanifest` (nombre "Mr Lana Tienda", `display: standalone`, `theme_color #73C83A`,
íconos 192/512/maskable, apple-touch-icon 180), service worker `public/sw.js` (cache de assets
estáticos de `/build` y offline fallback `/offline.html`; NUNCA cachear respuestas HTML/Inertia autenticadas,
POST, ni `/admin`, `/checkout`, `/mi-cuenta`). Meta tags iOS (`apple-mobile-web-app-capable`, status bar),
prompt de instalación discreto en Android (beforeinstallprompt) e instrucciones "Agregar a inicio" para iOS.

## 9. Catálogo PDF

Fuente: `docs/catalogo/Catalogo_muebles_Mexico_2026.pdf` (25 modelos, referencias IKEA MX, precios de
referencia al 04/11/2026). Datos extraídos a `database/data/catalogo_muebles_mx_2026.json`. Importación
idempotente con `php artisan catalogo:importar-muebles` (también llamada desde un seeder de producción
`CatalogoMueblesSeeder`) usando `importacion_clave`. Productos quedan `estado_publicacion=pendiente_aprobacion`,
`activo=true`, `visible=false`, `precio=null`, `stock=0`, `modalidad_venta` sin decidir (`inventario` por defecto),
sin imágenes (las fotos del PDF son de IKEA; no se extraen ni reutilizan), `precio_referencia` y
`fuente_referencia` llenos SÓLO para el admin. Marca: no se asigna "IKEA" como marca comercial; el modelo y
código quedan en `modelo`/`codigo_referencia`. Reimportar nunca sobreescribe campos que el admin ya editó
(precio, stock, imágenes, estado, descripción si fue modificada → usar sólo `firstOrCreate`/llenar nulos).

## 10. Pruebas

`tests/Pest.php` con `RefreshDatabase`. Factories para Producto, Categoria, Marca, Pedido, Pago, Cupon, Direccion.
Cada equipo agrega pruebas en `tests/Feature/<Area>/`. `Mail::fake()`, `Storage::fake('local')`, fake de PdfService.

## 11. Fundación backend — implementado (referencia para todos los equipos)

Estado: secciones 0–4 y 10 implementadas. Lo de aquí precisa (no contradice) lo anterior.

**Enums** (`app/Enums`, backed string, con `label()` en español, `color()` = tono UI `neutral|info|warning|success|danger|brand`,
y helpers `values()`, `options()`, `labelDe(?string)`, `toArray()`): `EstatusPedido`, `EstadoPago`, `EstadoLogistico`,
`EstadoEnvio`, `EstadoPagoRegistro` (estatus de la tabla `pagos`), `MetodoPagoClave`, `ModalidadEntrega`, `ModalidadVenta`,
`EstadoPublicacion`, `TipoMovimientoInventario`, `TipoReglaEnvio`.
Las columnas de estado **NO se castean a enum** (siguen siendo string) para tolerar históricos (`openpay`, `completado`, ...):
comparar con `Enum::X->value` y etiquetar con `Enum::labelDe($valor)`.

**Modelos nuevos**: `InventarioMovimiento`, `PagoComprobante` (constantes `EXTENSIONES`, `MIMES`, `MAX_KB`; `ruta`/`disk` ocultos),
`ReglaEnvio` (`activas()`, `aplicaA(estado, cp)`), `NotificacionPedido::registrarUnaVez(Pedido, tipo, clave): bool` (dedupe de correos).
`Pedido`: `recalcularEstatus()`, `pagoActual()`, `comprobantes()`, `scopeVencibles()`, `esContraEntrega()`, `esRecogerEnTienda()`, `esTerminal()`.
`Producto`: `publicados()`, `vendibles()`, `estaPublicado()`, `estaDisponible(int)`, `faltantesParaPublicar(): list<string>`,
`imagenPrincipalUrl()`, `esBajoPedido()`; oculta `costo`/`precio_referencia`/`fuente_referencia` al serializar.
Ojo: dentro de un modelo Eloquent `$this->visible` es la propiedad de serialización; usar `getAttribute('visible')`.
`MetodoPago`: `paraCheckout()`, `estaBloqueado()`, `esClaveDeSistema()`, `config(clave)`. URL de imágenes: `App\Support\ImagenUrl::resolver()`.

**Servicios (firmas públicas)**
- `Catalogo\PrecioService`: `calcular(Producto): array{precio_original, precio_final, descuento, oferta, tiene_oferta}`;
  `calcularMuchos(Collection): Collection` indexada por `producto_id` (≤ 2 consultas). Prioridad: ofertas directas del producto;
  si no hay, la de mayor descuento entre categoría y marca (empate → categoría). Categoría = coincidencia exacta de `categoria_id`.
- `Carrito\CarritoService`: `lineas()`, `cantidadItems()`, `estaVacio()`, `agregar(id, cant)`, `actualizar(id, cant)` (0 = quitar),
  `eliminar(id)`, `vaciar()`, `aplicarCupon(codigo, ?User)`, `quitarCupon()`, `codigoCupon()`,
  `resumen(?User, modalidad='envio_domicilio', ?Direccion)` y `cotizar(lineas, ?cupon, ?User, modalidad, ?Direccion)` →
  `{items[], cupon, cupon_error, envio, resumen{subtotal, subtotal_original, envio, envio_estado, envio_por_cotizar, descuento,
  descuento_ofertas, descuento_cupon, total, total_productos}, errores[], puede_comprar}`. Cada item trae `imagen`, `precio`,
  `precio_original`, `stock`, `cantidad_maxima`, `modalidad_venta`, `tiempo_entrega_dias`, `disponible`, `error`.
  Sesión: `carrito` = `{producto_id: {producto_id, cantidad}}`, `carrito_cupon` = código (string).
  Semántica: `total = subtotal(con ofertas) − cupón + envío`; `descuento = ofertas + cupón`.
- `Cupones\CuponService`: `validar(codigo, subtotal, ?User)` (lanza `CuponInvalidoException`), `calcularDescuento(Cupon, subtotal)`,
  `registrarUso(Cupon, Pedido, ?User)` (lock), `liberarUso(Pedido)` (al cancelar/vencer el uso se devuelve).
- `Envios\EnvioService`: `cotizar(Direccion|array|null, modalidad, subtotal, ?items)`, `modalidadesDisponibles()`, `recogerDisponible()`
  (regla `recoger_en_tienda` activa o `recoger.direccion` configurada), `permiteContraEntrega(cotizacion, total, ?estado)`.
- `Inventario\InventarioService`: `entrada`, `salida`, `devolucion`, `ajustar(±)`, `establecer(nuevoStock)`, `reservarParaPedido(Pedido)`,
  `liberarDePedido(Pedido)`. **Catálogo debe dejar de escribir `productos.stock` directo** (hoy lo hace `Admin\ProductoController`).
- `Configuracion\ConfiguracionTienda` (scoped, caché `configuracion_tienda.v1`; el modelo `Configuracion` invalida la caché al guardar):
  `get/string/array/bool/int`, `set`, `setMuchos`, `olvidar`, `nombre()`, `contacto()`, `redes()`, `banco()`, `bancoConfigurado()`,
  `recoger()`, `politicas()`, `publico()`, `clabeValida()` (+ regla `App\Rules\ClabeValida`).
- `Pedidos\PedidoWorkflow`: `crearDesdeCheckout(User, App\Data\DatosCheckout)`, `cotizarEnvio(Pedido, monto, User, ?nota)`,
  `registrarComprobante(Pedido, User, UploadedFile, ?ref, ?fecha, ?monto): PagoComprobante`, `aprobarPago(Pago, User, ?notas)`,
  `rechazarPago(Pago, User, motivo)`, `confirmarEfectivo(Pago, User, montoRecibido, ?notas)`, `registrarReembolso(Pedido, User, ?nota)`,
  `iniciarPreparacion`, `marcarListoEnvio`, `marcarListoRecoger`, `registrarEnvio(Pedido, User, paqueteria, guia, ?fecha)`,
  `confirmarEntrega(Pedido, User, ?montoRecibido, ?notas)`, `registrarIncidencia(Pedido, User, nota)`, `cancelar(Pedido, User, motivo)`,
  `vencer(Pedido): bool`, `agregarNota(Pedido, User, nota, visibleCliente=false)`,
  `accionesDisponibles(Pedido, User): array<string,bool>` (para mostrar botones; no sustituye las guardas).
  Los gates se verifican DENTRO del workflow (`AuthorizationException` → 403). Errores de regla: `TransicionInvalidaException`
  (todas las de `App\Exceptions` extienden `DominioException`; `bootstrap/app.php` las convierte en `back()->with('error', msg)` o JSON 422).

**Eventos** (`App\Events\Pedidos\*`, `ShouldDispatchAfterCommit`, propiedades públicas `pedido` y `actor` + extra; `claveNotificacion()` sugerida):
`PedidoRegistrado`, `ComprobanteRecibido(pago, comprobante)`, `PagoAprobado(pago)`, `PagoRechazado(pago, motivo)`, `EnvioCotizado(monto)`,
`PedidoEnPreparacion`, `PedidoEnviado`, `PedidoEntregado`, `PedidoCancelado(motivo, requiereReembolso)`, `PedidoVencido`,
y extra `PedidoListoParaRecoger`. No hay listeners: los escribe el equipo de notificaciones.

**Props compartidas Inertia**: `auth.can` = `{gestionar_catalogo, gestionar_configuracion, gestionar_usuarios, revisar_pagos,
operar_pedidos, cotizar_envio, cancelar_pedidos}`; `carrito.cantidad_items` (se eliminó `carrito.subtotal`);
`tienda` = `{nombre, eslogan, contacto{whatsapp, telefono, correo, horario, direccion}, redes{facebook, instagram, tiktok}, enlaces[{titulo, url}]}`
(sin banco; `enlaces` = páginas `politicas.*` con contenido + FAQ, los usa StoreFooter en todas las páginas públicas; la prop
`enlacesInformativos` y el trait `EnlacesInformativos` se eliminaron); `seo` = `App\Support\Seo::toArray()` (ver §12).

**Checkout actual** (`CheckoutController`, a rediseñar por Compra): `store` acepta `metodo_pago` (default `transferencia`) y
`modalidad_entrega` (default `envio_domicilio`); `index` añade props `envio`, `errores`, `puede_comprar`, `modalidades_entrega`,
`metodos_pago[{clave,nombre,instrucciones,disponible}]`, `direccion_id`; `gracias` añade `instrucciones{texto, banco (sólo dueño y
transferencia), recoger}` y conserva `openpay` con nulos sólo por compatibilidad con la vista vieja.

**Decisiones/precisiones**
- Pedidos legacy migrados quedan con `metodo_pago=openpay`, `vence_en=null`: no vencen solos; el admin decide.
- `vencer` sólo aplica a `pendiente_pago` con `estado_pago` pendiente|rechazado y sin comprobante en estado `recibido`.
  Pedidos `por_cotizar` no tienen `vence_en` hasta que se cotiza el envío.
- Contra entrega requiere método activo + regla de envío con `permite_contra_entrega` + estados/monto máximo del método.
  `confirmarEntrega` de contra entrega con `montoRecibido` exige además `revisar-pagos` (admin).
- `recalcularEstatus()`: incidencia/devuelto conservan el estatus general previo; `listo_recoger` → `listo_envio`.
- `MetodosPagoSeeder` NO pisa `activo`/configuración existentes (crea faltantes y completa claves); Openpay siempre inactivo+bloqueado.
  `Admin\MetodoPagoController` rechaza activar `openpay`, renombrar claves de sistema y borrar métodos con pagos.
- `DatabaseSeeder` ya no crea usuarios. Admin: `php artisan tienda:crear-admin [--nombre --correo --password --rol=admin|vendedor --actualizar]`.
  Demo local: `php artisan db:seed --class=ProductoSeeder` (no corre fuera de `APP_ENV=local`). `CatalogoMueblesSeeder` se llama si existe.
- Scheduler: `pedidos:vencer` cada 15 min (`routes/console.php`); el servidor necesita `* * * * * php artisan schedule:run`.
- MariaDB/MySQL de WAMP local usan **MyISAM** por defecto (sin FKs ni bloqueos de fila). Producción debe estar en InnoDB; los `down()` toleran ambos.
- Helpers de pruebas (`tests/Helpers/Dominio.php`): `sembrarMetodosPago()`, `adminTienda()`, `vendedorTienda()`, `clienteTienda()`,
  `crearPedidoCheckout(cliente, [[producto, cant]], metodo, modalidad, ?direccion, ?cupon)`. Factories: Producto (por defecto publicado;
  estados `borrador`, `pendienteAprobacion`, `pausado`, `bajoPedido`, `agotado`, `precio()`, `stock()`), Categoria, Marca, Direccion,
  Cupon (`vencido`, `agotado`, `montoFijo`), ReglaEnvio (`cotizacionManual`, `recoger`), Pedido (`aprobado`), PedidoItem, Pago.
- Pendiente de otros equipos: `Admin\PedidoController`, `Vendedor\PedidoController`, `Admin\PagoController` y `DashboardController` aún usan
  estatus viejos (`pendiente`, `pagado`, `preparando`) y escriben estados directo: deben migrar a `PedidoWorkflow`.
  `Admin\ProductoController::store` crea productos con `estado_publicacion=borrador` (default de BD): ya no se ven en la tienda hasta publicarlos.

## 12. SEO y vistas previas al compartir

Sin SSR de Inertia: `resources/views/app.blade.php` renderiza en el HTML del servidor title, description, canonical,
robots, Open Graph (`og:*`, `product:*`), Twitter y JSON-LD a partir de la prop `seo` (`App\Support\Seo`). El middleware
comparte un `seo` por defecto (noindex fuera de `Seo::RUTAS_INDEXABLES`); los controladores públicos envían el suyo
(`Seo::nuevo()->titulo()->descripcion()->canonical()->imagen()->producto()->conOrganizacion()->conMigas()->toArray()`).
`SeoHead.vue` (montado en PublicLayout) repite las mismas etiquetas con el mismo `head-key`/`data-inertia` para la
navegación en cliente; el JSON-LD sólo lo emite el servidor. URLs absolutas con `Seo::absoluta()` (APP_URL).
Config `config/seo.php` (`indexar` = `SEO_INDEXAR` o `APP_ENV=production`). `/robots.txt` es dinámico (no existe
`public/robots.txt`). Imagen OG general `public/icons/og-image.jpg`; por producto `App\Services\Seo\ImagenOgProducto`
(JPG 1200x630 en disco public `og/productos/`, regenerada desde `ImagenesProducto::sincronizarPrincipal`).
Pruebas en `tests/Feature/Seo`.

## 13. Cotización de envío con varias paqueterías

Decisión del dueño: el envío a domicilio se cotiza por separado; se le envían al cliente opciones de paquetería
(precio y tiempo) y él elige según qué tanto le urja. Las reglas automáticas (tarifa fija, recoger en tienda) siguen
disponibles; **sin reglas, todo pedido a domicilio queda `por_cotizar`** (no cambia).

**Flujo** (`PedidoWorkflow`, todo con transacción + `lockForUpdate`):
1. `por_cotizar` (estatus `pendiente_cotizacion`) → admin/vendedor (gate `cotizar-envio`) llama
   `enviarOpcionesEnvio(Pedido, User, opciones[], ?motivo)`: 1–8 opciones `{paqueteria_id (activa), servicio, costo, dias_min?,
   dias_max?, tiempo_texto?, notas?, recomendada?}` (una sola recomendada). Reemplaza las opciones previas, deja
   `envio_estado=opciones_enviadas` (estatus `eleccion_envio`), `vence_en=null`, historial visible y evento
   `OpcionesEnvioEnviadas(opcionIds, reemplazo)` → correo `CotizacionEnvioListaMail` («Tu cotización de envío está lista»),
   dedupe por juego de opciones (clave con el id mayor): reintentos no duplican; re-cotizar sí vuelve a avisar.
2. El cliente (dueño) en `checkout.gracias` o `cliente.pedidos.show` ve tarjetas (insignias *Recomendada*, *Más económica*,
   *Más rápida* — sólo si hay diferencia) y hace `POST cliente.pedidos.envio.elegir {opcion_id}` →
   `elegirOpcionEnvio`: pedido ajeno 404 (controlador) / `AuthorizationException` (workflow); opción de otro pedido,
   segunda elección o estado distinto → `TransicionInvalidaException`. Fija `seleccionada_en`, `envio`, `paqueteria`
   (nombre copiado), `total`, `saldo_pendiente`, pagos abiertos, `envio_estado=cotizado`, `vence_en` (transferencia/efectivo
   presencial; contra entrega no vence) → estatus `pendiente_pago` (contra entrega → `confirmado`). Evento `EnvioElegido(opcion)`:
   transferencia → `InstruccionesTransferenciaMail` (el mismo de registro, que no se envió por estar pendiente el envío; clave por
   opción, sin duplicar); otros métodos → `EnvioCotizadoMail` con sus instrucciones.
3. Mientras `envioPendiente()`: sin instrucciones de pago, sin datos bancarios, sin subir comprobante (`registrarComprobante`,
   `aprobarPago`, `confirmarEfectivo`, `iniciarPreparacion` lo rechazan).
4. **Re-cotizar**: antes de elegir, libre (motivo opcional). Después de elegir, sólo con motivo (≥5) y si `puedeRecotizar()`:
   no terminal, a domicilio, `estado_logistico=pendiente` y `estado_pago` pendiente|rechazado|contra_entrega (sin comprobante en
   revisión ni pago aprobado). Se resta el costo elegido del total, se limpian `paqueteria` y `vence_en` y el cliente vuelve a
   elegir. En cualquier otro caso, cancelar el pedido. `cotizarEnvio(monto)` (costo directo) se conserva.
   Acciones: `accionesDisponibles()` añade `cotizar_envio` (envío pendiente y a domicilio) y `recotizar_envio`.
5. `registrarEnvio`: el formulario precarga la paquetería elegida (`pedidos.paqueteria`). Enlace de rastreo
   `Pedido::urlRastreo()`: paquetería del catálogo (la elegida o por nombre) con `url_rastreo` con `{guia}` (guía urlencoded);
   respaldo: enlaces conocidos de `PedidoEnviadoMail::urlRastreo()`. Se muestra en Mis pedidos (`pedido.url_rastreo`), panel,
   PDF y correo de enviado.

**Panel**: `/admin/paqueterias` (gate `gestionar-configuracion`; borrar una usada sólo la desactiva). Detalle de pedido: sección
«Cotización de envío» (formulario dinámico `CotizadorEnvio.vue`, vista `OpcionesEnvioLista.vue` con la elegida). Envíos: pestañas
«Por cotizar» (diálogo con el formulario) y «Esperando al cliente». Dashboard: KPI `operacion.esperando_eleccion` y aviso
«Esperando elección del cliente» en *Requiere atención*. Ruta `POST admin.pedidos.opciones-envio` (`can:cotizar-envio`).

**Dirección del pedido**: `pedidos.direccion_envio` guarda la copia al comprar; editar una dirección ya **no** altera pedidos.
Ahora también se permite **borrar** direcciones usadas si todos sus pedidos tienen copia (`direccion_id` pasa a null por la FK).

**Pendiente**: los pedidos en `opciones_enviadas` no vencen solos (stock reservado); el tablero los marca para dar seguimiento.
Pruebas: `tests/Feature/Envios/CotizacionPaqueteriasTest.php`.
