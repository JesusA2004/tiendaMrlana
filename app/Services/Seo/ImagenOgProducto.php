<?php

namespace App\Services\Seo;

use App\Models\Producto;
use App\Services\Catalogo\PrecioService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imagen Open Graph de producto (JPG 1200x630 < 300 KB) para vistas previas al compartir
 * (WhatsApp, Facebook, X, Telegram, iMessage).
 *
 * Se compone con GD (fuentes de marca en resources/fonts): fondo verde profundo con rombos,
 * tarjeta blanca con la foto principal completa (sin recortar) en la zona segura central
 * (x 290–910, así el recorte cuadrado de WhatsApp la muestra entera), nombre del producto,
 * logo Mr Lana y pastilla con el precio publicado (o el dominio si no hay precio).
 * Se guarda en el disco `public` como `og/productos/{id}-{hash}.jpg`; el hash depende de la
 * foto principal, el nombre y el precio, así que al cambiar se genera una nueva y se borran las previas.
 * Si algo falla devuelve null y el llamador usa la imagen general.
 */
class ImagenOgProducto
{
    public const DISCO = 'public';

    public const DIRECTORIO = 'og/productos';

    public const ANCHO = 1200;

    public const ALTO = 630;

    /** Cambiar si se modifica el diseño (fuerza regenerar todas). */
    private const VERSION = 'v2';

    private const CALIDAD = 84;

    private const MAX_PIXELES = 36_000_000;

    /**
     * Imagen OG del producto; la genera si falta.
     *
     * @return array{url: string, ancho: int, alto: int, tipo: string}|null
     */
    public function para(Producto $producto, ?string $rutaPrincipal = null, bool $generarSiFalta = true): ?array
    {
        $origen = $this->rutaLocal($rutaPrincipal ?? $this->rutaPrincipal($producto));

        if ($origen === null || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $disco = Storage::disk(self::DISCO);
        $precio = $this->precioPublicado($producto);
        $destino = $this->destino($producto, $origen, $precio);

        if (! $disco->exists($destino)) {
            if (! $generarSiFalta || ! $disco->exists($origen)) {
                return null;
            }

            try {
                $jpg = $this->componer((string) $disco->get($origen), $producto, $precio);
            } catch (Throwable $e) {
                Log::warning('No se pudo generar la imagen OG del producto', ['producto' => $producto->id, 'error' => $e->getMessage()]);
                $jpg = null;
            }

            if ($jpg === null) {
                return null;
            }

            $disco->put($destino, $jpg, ['visibility' => 'public']);
            $this->borrarAnteriores($producto, $destino);
        }

        return [
            'url' => $disco->url($destino),
            'ancho' => self::ANCHO,
            'alto' => self::ALTO,
            'tipo' => 'image/jpeg',
        ];
    }

    /** Pre-genera la OG tras cambiar la imagen principal. Nunca lanza excepciones. */
    public function regenerar(Producto $producto): void
    {
        try {
            if ($this->para($producto) === null) {
                $this->olvidar($producto);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo regenerar la imagen OG del producto', ['producto' => $producto->id, 'error' => $e->getMessage()]);
        }
    }

    /** Borra todas las imágenes OG del producto. */
    public function olvidar(Producto $producto): void
    {
        $this->borrarAnteriores($producto, null);
    }

    // ------------------------------------------------------------------ internos

    /** El archivo depende de la foto, el nombre y el precio publicado: si cambian, se regenera. */
    private function destino(Producto $producto, string $origen, ?float $precio): string
    {
        return self::DIRECTORIO.'/'.$producto->id.'-'.substr(sha1(self::VERSION.'|'.$origen.'|'.$producto->nombre.'|'.$precio), 0, 12).'.jpg';
    }

    private function precioPublicado(Producto $producto): ?float
    {
        try {
            $final = app(PrecioService::class)->calcular($producto)['precio_final'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return $final !== null && (float) $final > 0 ? round((float) $final, 2) : null;
    }

    private function borrarAnteriores(Producto $producto, ?string $conservar): void
    {
        $disco = Storage::disk(self::DISCO);

        foreach ($disco->files(self::DIRECTORIO) as $archivo) {
            if ($archivo !== $conservar && str_starts_with(basename($archivo), $producto->id.'-')) {
                $disco->delete($archivo);
            }
        }
    }

    private function rutaPrincipal(Producto $producto): ?string
    {
        $imagenes = $producto->relationLoaded('imagenes')
            ? $producto->imagenes
            : $producto->imagenes()->get(['id', 'producto_id', 'ruta', 'orden', 'principal']);

        return $imagenes->sortBy([['principal', 'desc'], ['orden', 'asc'], ['id', 'asc']])->pluck('ruta')->filter()->first()
            ?: $producto->imagen_principal;
    }

    /** Ruta relativa al disco `public` o null si la imagen no es local (URL externa). */
    private function rutaLocal(?string $ruta): ?string
    {
        $ruta = $ruta !== null ? trim($ruta) : '';

        if ($ruta === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $ruta)) {
            $ruta = (string) parse_url($ruta, PHP_URL_PATH);

            if (! str_starts_with($ruta, '/storage/')) {
                return null;
            }
        }

        $ruta = preg_replace('#^/?storage/#', '', $ruta) ?? $ruta;

        return str_contains($ruta, '..') ? null : ltrim($ruta, '/');
    }

    private function componer(string $datos, Producto $producto, ?float $precio): ?string
    {
        $info = @getimagesizefromstring($datos);

        if (! $info || $info[0] * $info[1] > self::MAX_PIXELES) {
            return null;
        }

        $foto = @imagecreatefromstring($datos);

        if ($foto === false) {
            return null;
        }

        $w = self::ANCHO;
        $h = self::ALTO;
        $lienzo = imagecreatetruecolor($w, $h);
        imagealphablending($lienzo, true);
        imageantialias($lienzo, false); // las líneas antialias de GD ignoran la transparencia

        // Fondo: degradado verde profundo de marca (#22573F → #12302A) con rombos tenues.
        for ($y = 0; $y < $h; $y++) {
            imageline($lienzo, 0, $y, $w, $y, $this->color($lienzo, [0x22, 0x57, 0x3F], [0x12, 0x30, 0x2A], $y / $h));
        }

        $linea = imagecolorallocatealpha($lienzo, 255, 255, 255, 116);
        for ($x = -$h; $x < $w + $h; $x += 56) {
            imageline($lienzo, $x, 0, $x + $h, $h, $linea);
            imageline($lienzo, $x, $h, $x + $h, 0, $linea);
        }
        imagefilledellipse($lienzo, 70, 560, 380, 380, imagecolorallocatealpha($lienzo, 0x64, 0xCB, 0xD4, 114));
        imagefilledellipse($lienzo, 1140, 60, 380, 380, imagecolorallocatealpha($lienzo, 0x73, 0xC8, 0x3A, 112));
        $this->rombo($lienzo, 160, 150, 14, imagecolorallocate($lienzo, 0x64, 0xCB, 0xD4));
        $this->rombo($lienzo, 1050, 470, 16, imagecolorallocate($lienzo, 0x73, 0xC8, 0x3A));

        // Zona segura central (x 290–910): tarjeta blanca con la foto completa (sin recortar).
        [$cx1, $cy1, $cx2, $cy2] = [290, 30, 910, 404];
        $this->rectanguloRedondeado($lienzo, $cx1, $cy1 + 10, $cx2, $cy2 + 10, 30, imagecolorallocatealpha($lienzo, 0, 0, 0, 96));
        $this->rectanguloRedondeado($lienzo, $cx1, $cy1, $cx2, $cy2, 30, imagecolorallocate($lienzo, 255, 255, 255));

        $relleno = 22;
        $maxW = ($cx2 - $cx1) - 2 * $relleno;
        $maxH = ($cy2 - $cy1) - 2 * $relleno;
        $fw = imagesx($foto);
        $fh = imagesy($foto);
        $escala = min($maxW / $fw, $maxH / $fh, 2.0);
        $dw = (int) round($fw * $escala);
        $dh = (int) round($fh * $escala);
        imagecopyresampled($lienzo, $foto, $cx1 + (int) (($cx2 - $cx1 - $dw) / 2), $cy1 + (int) (($cy2 - $cy1 - $dh) / 2), 0, 0, $dw, $dh, $fw, $fh);
        imagedestroy($foto);

        $baloo = resource_path('fonts/Baloo2-ExtraBold.ttf');
        $montserrat = resource_path('fonts/Montserrat-Bold.ttf');
        $hayFuentes = function_exists('imagettftext') && is_file($baloo) && is_file($montserrat);

        // Nombre del producto (máx. 2 líneas, centrado en la zona segura).
        if ($hayFuentes) {
            $blanco = imagecolorallocate($lienzo, 255, 255, 255);
            $lineas = $this->lineas((string) $producto->nombre, $baloo, 28, 600, 2);
            $y = count($lineas) === 1 ? 464 : 448;
            foreach ($lineas as $texto) {
                $this->textoCentrado($lienzo, $texto, $baloo, 28, $y, $blanco, $w / 2);
                $y += 36;
            }
        }

        // Fila inferior: logo real + pastilla con el precio publicado (o el dominio).
        $rutaLogo = resource_path('js/img/logo.png');
        $logo = is_file($rutaLogo) ? @imagecreatefrompng($rutaLogo) : false;
        $lh = 50;
        $lw = $logo !== false ? (int) round(imagesx($logo) * $lh / imagesy($logo)) : 0;

        $conPrecio = $precio !== null && $precio > 0;
        $etiqueta = $conPrecio
            ? '$'.number_format((float) $precio, 2).' MXN'
            : (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'Mr Lana');
        $tam = $conPrecio ? 22 : 17;
        $pw = $hayFuentes ? $this->anchoTexto($etiqueta, $montserrat, $tam) + 44 : 0;
        $ph = 50;
        $gap = 22;
        $x = (int) round(($w - ($lw + ($hayFuentes ? $gap + $pw : 0))) / 2);
        $yFila = 530;

        if ($logo !== false) {
            imagecopyresampled($lienzo, $logo, $x, $yFila, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }

        if ($hayFuentes) {
            $px = $x + $lw + $gap;
            $py = $yFila + 2;
            for ($i = 0; $i <= $pw; $i++) {
                $alto = $this->altoEnBorde($i, $pw, $ph);
                $c = $this->color($lienzo, [0x73, 0xC8, 0x3A], [0x64, 0xCB, 0xD4], $i / max(1, $pw));
                imageline($lienzo, $px + $i, $py + intdiv($ph - $alto, 2), $px + $i, $py + intdiv($ph + $alto, 2), $c);
            }
            $tinta = imagecolorallocate($lienzo, 0x0A, 0x1F, 0x17);
            imagettftext($lienzo, $tam, 0, $px + 22, $py + (int) round($ph / 2 + $tam * 0.37), $tinta, $montserrat, $etiqueta);
        }

        // Franja inferior con el degradado de marca (#73C83A → #64CBD4).
        for ($x = 0; $x < $w; $x++) {
            imageline($lienzo, $x, $h - 8, $x, $h - 1, $this->color($lienzo, [0x73, 0xC8, 0x3A], [0x64, 0xCB, 0xD4], $x / $w));
        }

        imageinterlace($lienzo, true);
        ob_start();
        $ok = imagejpeg($lienzo, null, self::CALIDAD);
        $jpg = (string) ob_get_clean();
        imagedestroy($lienzo);

        return $ok && $jpg !== '' ? $jpg : null;
    }

    private function rectanguloRedondeado(\GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($img, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$x, $y]) {
            imagefilledarc($img, $x, $y, $r * 2, $r * 2, 0, 360, $color, IMG_ARC_PIE);
        }
    }

    private function rombo(\GdImage $img, int $cx, int $cy, int $r, int $color): void
    {
        imagefilledpolygon($img, [$cx, $cy - $r, $cx + $r, $cy, $cx, $cy + $r, $cx - $r, $cy], $color);
    }

    /** Alto de la columna $i en una pastilla con extremos semicirculares. */
    private function altoEnBorde(int $i, int $ancho, int $alto): int
    {
        $r = $alto / 2;
        $d = min($i, $ancho - $i);

        return $d >= $r ? $alto : (int) round(2 * sqrt(max(0, $r * $r - ($r - $d) ** 2)));
    }

    private function anchoTexto(string $texto, string $fuente, int $tam): int
    {
        $caja = imagettfbbox($tam, 0, $fuente, $texto);

        return $caja === false ? 0 : (int) abs($caja[2] - $caja[0]);
    }

    private function textoCentrado(\GdImage $img, string $texto, string $fuente, int $tam, int $y, int $color, float $centro): void
    {
        imagettftext($img, $tam, 0, (int) round($centro - $this->anchoTexto($texto, $fuente, $tam) / 2), $y, $color, $fuente, $texto);
    }

    /**
     * Parte el texto en líneas que caben en $ancho (máx. $max; si sobra, la última termina en "…").
     *
     * @return list<string>
     */
    private function lineas(string $texto, string $fuente, int $tam, int $ancho, int $max): array
    {
        $lineas = [];
        $actual = '';

        foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual.' '.$palabra;

            if ($actual !== '' && $this->anchoTexto($prueba, $fuente, $tam) > $ancho) {
                $lineas[] = $actual;
                $actual = $palabra;
            } else {
                $actual = $prueba;
            }
        }

        if ($actual !== '') {
            $lineas[] = $actual;
        }

        if (count($lineas) > $max) {
            $lineas = array_slice($lineas, 0, $max);
            $lineas[$max - 1] .= '…';
        }

        return array_map(function (string $linea) use ($fuente, $tam, $ancho) {
            while (mb_strlen($linea) > 2 && $this->anchoTexto($linea, $fuente, $tam) > $ancho) {
                $linea = rtrim(mb_substr($linea, 0, -2), ' …').'…';
            }

            return $linea;
        }, $lineas);
    }

    /**
     * @param  array{int, int, int}  $a
     * @param  array{int, int, int}  $b
     */
    private function color(\GdImage $img, array $a, array $b, float $t): int
    {
        return imagecolorallocate(
            $img,
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        );
    }
}
