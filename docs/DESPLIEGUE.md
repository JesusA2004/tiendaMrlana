# Despliegue — Tienda Mr Lana

Guía para actualizar el VPS (`pruebas.mr-lana.com` y después producción). Ningún paso
contiene credenciales: se capturan en el servidor. **No ejecutar `migrate:fresh`,
`migrate:reset`, `migrate:rollback` ni `db:wipe` en producción.**

## 1. Requisitos del servidor

| Componente | Versión / nota |
|---|---|
| PHP | 8.3+ con `pdo_mysql`, `mbstring`, `gd` (WebP), `fileinfo`, `intl`, `bcmath`, `zip` |
| MariaDB | 11.4, **tablas InnoDB** (FKs y `SELECT … FOR UPDATE` para stock y pagos) |
| Node.js | 20+ (build de Vite y Browsershot/puppeteer en tiempo de ejecución) |
| Chrome headless | instalado por puppeteer (ver §4) + librerías del sistema (`libnss3`, `libatk-bridge2.0-0`, `libgbm1`, `libasound2`, `libxkbcommon0`, `fonts-liberation`) |
| Cola | `php artisan queue:work` supervisado (correos) |
| Scheduler | cron cada minuto (`pedidos:vencer` cada 15 min) |

Verificar motor: `SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='tiendamrlana';`
Si alguna tabla es MyISAM: `ALTER TABLE <tabla> ENGINE=InnoDB;` (con respaldo previo).

## 2. Respaldo (antes de cada despliegue)

```bash
cd /ruta/tiendaMrlana
TS=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --routines --triggers -u <usuario> -p tiendamrlana > ~/respaldos/tiendamrlana-$TS.sql
tar czf ~/respaldos/storage-$TS.tgz storage/app        # incluye comprobantes privados e imágenes
git rev-parse HEAD > ~/respaldos/commit-$TS.txt
```

## 3. Actualización

```bash
php artisan down --refresh=15
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npx puppeteer browsers install chrome-headless-shell   # Chrome para PDFs (Browsershot)
npm run build
php artisan migrate --force                             # sólo migraciones aditivas 2026_11_05_*
php artisan storage:link                                 # si no existe public/storage (las fotos se sirven desde ahí)
php artisan db:seed --class=MetodosPagoSeeder --force   # crea métodos faltantes; no pisa los existentes
php artisan catalogo:importar-muebles --imagenes --publicar  # 25 muebles con fotos y precio del catálogo, publicados (idempotente)
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
php artisan up
```

> No ejecutar `php artisan db:seed` sin `--class`: `DatabaseSeeder` ya no crea usuarios, pero
> conviene correr sólo los seeders indicados.

### 3.1 Catálogo de muebles 2026 (fotos + precios del catálogo, publicado)

Decisión del dueño: los 25 muebles de `docs/catalogo/Catalogo_muebles_Mexico_2026.pdf` se publican con
las fotos del PDF (ya extraídas al repo en `database/data/catalogo_muebles_mx_2026/imagenes/`, manifiesto
`imagenes.json`) y con el precio del catálogo como precio de venta. Después de `git pull`, `migrate` y
`storage:link`:

```bash
php artisan catalogo:importar-muebles --imagenes --publicar
```

- Idempotente: re-ejecutarlo no duplica fotos (sólo adjunta a modelos **sin** imágenes), no pisa precios
  editados en el panel (sólo llena precio vacío) y no toca productos ya publicados o **pausados** por el admin.
- Sin existencias capturadas los muebles quedan **"Bajo pedido"** (sin días de entrega hasta que el admin
  los capture). Para venderlos de inventario: panel → Catálogo → editar existencias/modalidad.
- Genera WebP en `storage/app/public/productos/{id}/` y la imagen para compartir en
  `storage/app/public/og/productos/` (requiere GD con WebP: `php -r 'var_dump(function_exists("imagewebp"));'`).
- `--dry-run` muestra lo que haría sin escribir. `--forzar-precios` re-sincroniza el precio de venta con el del
  catálogo aunque el admin ya lo haya cambiado (no usar salvo que el dueño lo pida).
- Alternativa vía seeder: `CATALOGO_MUEBLES_PUBLICAR=true` en `.env` + `php artisan config:clear` +
  `php artisan db:seed --class=CatalogoMueblesSeeder --force` (sin la variable sólo crea/actualiza como pendientes).
- Si algún modelo no se publica, la salida lo lista con el motivo ("No publicado ML-MUE-…: …").

Verificación:

```bash
php artisan catalogo:importar-muebles --imagenes --publicar   # 2ª corrida: "0 con cambios, 25 sin cambios"
php artisan tinker --execute='echo App\Models\Producto::publicados()->count();'   # 25 (o más si hay otros productos)
ls storage/app/public/productos | wc -l                       # una carpeta por producto con fotos
curl -sI https://tienda.mr-lana.com/storage/$(php artisan tinker --execute='echo App\Models\Producto::where("sku","ML-MUE-90243297")->value("imagen_principal");') | head -1   # 200
```

En el navegador: `/productos` muestra los muebles con foto, precio y "Bajo pedido".

Primer administrador (si no existe uno con contraseña segura):

```bash
php artisan tienda:crear-admin   # interactivo; pide nombre, correo y contraseña fuerte
```

**Seguridad:** si en el servidor existen las cuentas antiguas `admin@mr-lana.com` /
`vendedor@mr-lana.com` creadas con la contraseña `password` por el seeder anterior,
cambiarles la contraseña o eliminarlas de inmediato:
`php artisan tienda:crear-admin --correo=admin@mr-lana.com --actualizar`.

## 4. Variables de entorno relevantes (`.env`)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tienda.mr-lana.com     # producción (pruebas: https://pruebas.mr-lana.com). Sin "/" final.
SEO_INDEXAR=false                    # SÓLO en pruebas.mr-lana.com si ahí APP_ENV=production (evita que Google lo indexe)
DB_CONNECTION=mariadb
QUEUE_CONNECTION=database
MAIL_MAILER=smtp  (+ MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS)
PAYMENTS_ONLINE_ENABLED=false
BROWSERSHOT_NODE_BINARY=/usr/bin/node
BROWSERSHOT_NPM_BINARY=/usr/bin/npm
BROWSERSHOT_NO_SANDBOX=true      # sólo si PHP corre como root
```

## 5. Permisos

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```
Los comprobantes se guardan en `storage/app/private/comprobantes` (disco `local`, nunca público).
El usuario de PHP-FPM necesita poder ejecutar Node y escribir en su `HOME` (caché de puppeteer).

## 6. Procesos permanentes

Cron (usuario del sitio):
```
* * * * * cd /ruta/tiendaMrlana && php artisan schedule:run >> /dev/null 2>&1
```

Supervisor `/etc/supervisor/conf.d/mrlana-worker.conf`:
```
[program:mrlana-worker]
command=php /ruta/tiendaMrlana/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/ruta/tiendaMrlana/storage/logs/worker.log
stopwaitsecs=3600
```

## 7. Configuración comercial después de desplegar (panel → Configuración)

1. Datos bancarios (banco, beneficiario, CLABE). Sin ellos, el cliente ve "Te contactaremos con los datos bancarios".
2. Contacto (correo para avisos internos, teléfono, WhatsApp, dirección) y recoger en tienda.
3. Reglas de envío. Sin reglas, todo pedido a domicilio queda "por cotizar".
4. Métodos de pago: transferencia y efectivo presencial; contra entrega sólo si se define zona.
5. Políticas, FAQ y beneficios del home.
6. Catálogo: los 25 muebles ya quedan publicados (§3.1) con precio del catálogo y "bajo pedido"; capturar existencias reales y ajustar precios si hace falta.
7. SEO: dar de alta `https://tienda.mr-lana.com/sitemap.xml` en Google Search Console (ver §8.1).

## 8. Verificación posterior

- `/up` responde 200; home, catálogo y `/sitemap.xml` cargan.
- Crear un pedido de prueba con transferencia → subir comprobante → aprobar en el panel → revisar correos en el buzón.
- Descargar el PDF del pedido (valida Chrome/Browsershot).
- `tail -f storage/logs/laravel.log` sin errores.

### 8.1 SEO y vistas previas al compartir (WhatsApp, Facebook, X, Telegram)

Las metaetiquetas (title, description, canonical, robots, Open Graph, Twitter, JSON-LD) las genera
el **servidor** en `resources/views/app.blade.php` a partir de la prop `seo` (`App\Support\Seo`);
no se usa SSR de Inertia (ningún proceso extra). Todas las URL salen de `APP_URL`, por eso debe ser
`https://tienda.mr-lana.com` en producción y hay que correr `php artisan config:cache` tras cambiarla.

- **robots.txt es dinámico** (ruta `/robots.txt`): **no** debe existir `public/robots.txt` (si existe,
  Apache/Nginx lo sirven y tapan la ruta). En producción bloquea áreas privadas y anuncia
  `Sitemap: https://tienda.mr-lana.com/sitemap.xml`; con `APP_ENV` distinto de `production`
  responde `Disallow: /` y todas las páginas llevan `noindex, nofollow`. **Si pruebas.mr-lana.com
  corre con `APP_ENV=production`, poner `SEO_INDEXAR=false` en su `.env`** (y `config:cache`).
  Nginx: si hay un `location = /robots.txt { ... }` con `try_files` sólo a archivo, cambiarlo a
  `try_files $uri /index.php?$query_string;` o quitarlo.
- **Imagen OG general**: `public/icons/og-image.jpg` (1200x630, JPG < 300 KB, todo lo importante en la
  zona central para el recorte cuadrado de WhatsApp). Se regenera con `npm run og:image`
  (`resources/og/og-image.html` + Puppeteer; fuentes de marca en `resources/fonts`).
- **Imagen OG por producto**: JPG 1200x630 con fondo de marca y la foto principal, en
  `storage/app/public/og/productos/{id}-{hash}.jpg` (requiere `php artisan storage:link` y que PHP
  pueda escribir en `storage/app/public`). Se regenera al cambiar la imagen principal desde el panel
  y, para productos existentes, se crea sola en la primera visita a la ficha. Incluir esa carpeta en
  los respaldos de `storage/app` (ya cubierto en §2).

Verificación después de desplegar:

```bash
curl -s https://tienda.mr-lana.com/robots.txt                     # debe incluir la línea Sitemap
curl -s https://pruebas.mr-lana.com/robots.txt                    # debe decir Disallow: /
curl -s -A "WhatsApp/2.23.20.0" https://tienda.mr-lana.com/productos/<slug> | grep -E 'og:|twitter:|canonical'
curl -sI https://tienda.mr-lana.com/icons/og-image.jpg             # 200, image/jpeg
```

Depuradores: Facebook Sharing Debugger (https://developers.facebook.com/tools/debug/ → "Scrape Again"
para refrescar la caché, que también usa WhatsApp), validador de datos estructurados
(https://validator.schema.org/) y Rich Results Test de Google. WhatsApp guarda en caché la vista
previa de cada URL: tras corregir algo, probar con la URL agregando `?v=2`.

### Aparecer en Google ("mr lana", "tienda mr lana")

El sitio ya emite título y descripción con la marca, H1 "Tienda Mr Lana" en el home, JSON-LD
`Organization`/`OnlineStore` + `WebSite` con `name: "Mr Lana"` y `alternateName` ("Tienda Mr Lana",
"Mr. Lana", "MrLana", "Mr Lana Tienda"; `config/seo.php`) y sitemap. Falta darlo de alta:

1. **Search Console** (https://search.google.com/search-console) → *Agregar propiedad*.
   - Recomendado: propiedad de **dominio** `mr-lana.com` verificada con un registro **TXT** en el DNS.
   - Alternativa: propiedad de **prefijo de URL** `https://tienda.mr-lana.com/` con *Etiqueta HTML*:
     copiar sólo el valor de `content` a `SEO_GOOGLE_VERIFICATION=` en `.env`, `php artisan config:cache`
     y pulsar *Verificar*. (Bing Webmaster Tools: igual con `SEO_BING_VERIFICATION=`, o importar desde
     Search Console).
2. **Sitemaps** → enviar `https://tienda.mr-lana.com/sitemap.xml`.
3. **Inspección de URLs** → `https://tienda.mr-lana.com/` → *Solicitar indexación* (repetir con el
   catálogo y 2–3 productos importantes).
4. **Google Business Profile** (https://business.google.com): crear/reclamar el perfil "Mr Lana" con la
   URL de la tienda y los datos reales del negocio; es lo que muestra el panel de marca en las búsquedas.
5. Enlazar `https://tienda.mr-lana.com` desde las redes sociales y el sitio principal de Mr Lana, y
   capturar esas redes en el panel (Configuración → redes): alimentan `sameAs` del JSON-LD.

Indexar toma de días a algunas semanas; el avance se ve en Search Console → *Páginas* y *Rendimiento*.

## 9. Reversión

```bash
php artisan down
git checkout <commit-anterior>           # de ~/respaldos/commit-*.txt
composer install --no-dev --optimize-autoloader && npm ci && npm run build
# Base de datos: preferir restaurar el respaldo completo
mysql -u <usuario> -p tiendamrlana < ~/respaldos/tiendamrlana-<TS>.sql
php artisan optimize:clear && php artisan queue:restart && php artisan up
```
Las migraciones nuevas tienen `down()` funcional (`php artisan migrate:rollback --step=N`),
pero con datos reales es más seguro restaurar el respaldo tomado en §2.
