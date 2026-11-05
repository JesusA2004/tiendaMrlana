<?php

namespace App\Services\Seo;

use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imagen Open Graph de producto (JPG 1200x630 < 300 KB) para vistas previas al compartir
 * (WhatsApp, Facebook, X, Telegram, iMessage).
 *
 * Se compone con GD: fondo verde profundo de marca, tarjeta blanca con la foto principal
 * centrada (sin recortar), logo Mr Lana y franja con el degradado verde → turquesa.
 * Se guarda en el disco `public` como `og/productos/{id}-{hash}.jpg`; el hash depende de la
 * ruta de la imagen principal, así que al cambiarla se genera una nueva y se borran las previas.
 * Si algo falla devuelve null y el llamador usa la imagen general.
 */
class ImagenOgProducto
{
    public const DISCO = 'public';

    public const DIRECTORIO = 'og/productos';

    public const ANCHO = 1200;

    public const ALTO = 630;

    /** Cambiar si se modifica el diseño (fuerza regenerar todas). */
    private const VERSION = 'v1';

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
        $destino = $this->destino($producto, $origen);

        if (! $disco->exists($destino)) {
            if (! $generarSiFalta || ! $disco->exists($origen)) {
                return null;
            }

            try {
                $jpg = $this->componer((string) $disco->get($origen));
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

    private function destino(Producto $producto, string $origen): string
    {
        return self::DIRECTORIO.'/'.$producto->id.'-'.substr(sha1(self::VERSION.'|'.$origen), 0, 12).'.jpg';
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

    private function componer(string $datos): ?string
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
        imageantialias($lienzo, true);

        // Fondo: degradado vertical verde profundo de marca (#1F4D3A → #14332B).
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            imageline($lienzo, 0, $y, $w, $y, $this->color($lienzo, [0x1F, 0x4D, 0x3A], [0x14, 0x33, 0x2B], $t));
        }

        // Tarjeta blanca redondeada.
        [$cx1, $cy1, $cx2, $cy2, $r] = [40, 34, $w - 40, 548, 28];
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefilledrectangle($lienzo, $cx1 + $r, $cy1, $cx2 - $r, $cy2, $blanco);
        imagefilledrectangle($lienzo, $cx1, $cy1 + $r, $cx2, $cy2 - $r, $blanco);
        foreach ([[$cx1 + $r, $cy1 + $r], [$cx2 - $r, $cy1 + $r], [$cx1 + $r, $cy2 - $r], [$cx2 - $r, $cy2 - $r]] as [$x, $y]) {
            imagefilledellipse($lienzo, $x, $y, $r * 2, $r * 2, $blanco);
        }

        // Foto centrada dentro de la tarjeta, sin recortar.
        $relleno = 24;
        $maxW = ($cx2 - $cx1) - 2 * $relleno;
        $maxH = ($cy2 - $cy1) - 2 * $relleno;
        $fw = imagesx($foto);
        $fh = imagesy($foto);
        $escala = min($maxW / $fw, $maxH / $fh, 2.0);
        $dw = (int) round($fw * $escala);
        $dh = (int) round($fh * $escala);
        imagecopyresampled($lienzo, $foto, $cx1 + (int) (($cx2 - $cx1 - $dw) / 2), $cy1 + (int) (($cy2 - $cy1 - $dh) / 2), 0, 0, $dw, $dh, $fw, $fh);
        imagedestroy($foto);

        // Logo Mr Lana (asset real de la marca) en la franja inferior.
        $rutaLogo = resource_path('js/img/logo.png');
        if (is_file($rutaLogo) && ($logo = @imagecreatefrompng($rutaLogo)) !== false) {
            $lh = 52;
            $lw = (int) round(imagesx($logo) * $lh / imagesy($logo));
            imagecopyresampled($lienzo, $logo, 46, 560, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
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
