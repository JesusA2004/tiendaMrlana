<?php

namespace App\Services\Catalogo;

use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Services\Seo\ImagenOgProducto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Galería de imágenes de producto (disco `public`): guarda optimizando a WebP con GD cuando
 * está disponible, mantiene orden/principal y sincroniza `productos.imagen_principal`.
 */
class ImagenesProducto
{
    public const DISCO = 'public';

    public const LADO_MAXIMO = 1600;

    public const CALIDAD_WEBP = 82;

    /** Límite de píxeles para procesar en memoria con GD (~ 6000 x 6000). */
    private const MAX_PIXELES = 36_000_000;

    /**
     * @param  list<UploadedFile>  $archivos
     * @return list<ProductoImagen>
     */
    public function agregar(Producto $producto, array $archivos): array
    {
        $creadas = [];
        $orden = (int) $producto->imagenes()->max('orden');
        $tienePrincipal = $producto->imagenes()->where('principal', true)->exists();

        foreach ($archivos as $archivo) {
            $ruta = $this->guardar($producto, $archivo);

            $creadas[] = $producto->imagenes()->create([
                'ruta' => $ruta,
                'orden' => ++$orden,
                'principal' => ! $tienePrincipal,
            ]);

            $tienePrincipal = true;
        }

        $this->sincronizarPrincipal($producto);

        return $creadas;
    }

    /**
     * @param  list<int>  $ids  ids en el nuevo orden
     */
    public function ordenar(Producto $producto, array $ids): void
    {
        DB::transaction(function () use ($producto, $ids) {
            $imagenes = $producto->imagenes()->get()->keyBy('id');
            $posicion = 1;

            foreach ($ids as $id) {
                if ($imagen = $imagenes->pull((int) $id)) {
                    $imagen->update(['orden' => $posicion++]);
                }
            }

            // Las que no vinieron en la lista conservan su orden relativo al final.
            foreach ($imagenes->sortBy('orden') as $imagen) {
                $imagen->update(['orden' => $posicion++]);
            }
        });

        $this->sincronizarPrincipal($producto);
    }

    public function marcarPrincipal(Producto $producto, ProductoImagen $imagen): void
    {
        DB::transaction(function () use ($producto, $imagen) {
            $producto->imagenes()->where('id', '!=', $imagen->id)->update(['principal' => false]);
            $imagen->update(['principal' => true]);
        });

        $this->sincronizarPrincipal($producto);
    }

    public function eliminar(Producto $producto, ProductoImagen $imagen): void
    {
        $ruta = $imagen->ruta;
        $eraPrincipal = $imagen->principal;
        $imagen->delete();

        $this->borrarArchivo($ruta);

        if ($eraPrincipal) {
            $siguiente = $producto->imagenes()->orderBy('orden')->orderBy('id')->first();
            $siguiente?->update(['principal' => true]);
        }

        if ($producto->imagen_principal === $ruta) {
            $producto->forceFill(['imagen_principal' => null])->save();
        }

        $this->sincronizarPrincipal($producto);
    }

    public function eliminarTodas(Producto $producto): void
    {
        foreach ($producto->imagenes()->get() as $imagen) {
            $this->borrarArchivo($imagen->ruta);
            $imagen->delete();
        }

        app(ImagenOgProducto::class)->olvidar($producto);
    }

    /**
     * `imagen_principal` refleja la imagen principal de la galería (si hay galería).
     */
    public function sincronizarPrincipal(Producto $producto): void
    {
        $principal = $producto->imagenes()
            ->orderByDesc('principal')->orderBy('orden')->orderBy('id')
            ->first();

        if ($principal && ! $principal->principal) {
            $principal->update(['principal' => true]);
        }

        if ($principal && $producto->imagen_principal !== $principal->ruta) {
            $producto->forceFill(['imagen_principal' => $principal->ruta])->save();
        }

        $producto->unsetRelation('imagenes');

        // Imagen para vistas previas al compartir (WhatsApp, Facebook…): se regenera si cambió la principal.
        app(ImagenOgProducto::class)->regenerar($producto);
    }

    public function guardar(Producto $producto, UploadedFile $archivo): string
    {
        $directorio = 'productos/'.$producto->id;
        $nombre = (string) Str::uuid();

        $webp = $this->convertirAWebp($archivo);

        if ($webp !== null) {
            $ruta = $directorio.'/'.$nombre.'.webp';
            Storage::disk(self::DISCO)->put($ruta, $webp);

            return $ruta;
        }

        $extension = strtolower($archivo->guessExtension() ?: $archivo->getClientOriginalExtension() ?: 'jpg');

        return $archivo->storeAs($directorio, $nombre.'.'.$extension, self::DISCO);
    }

    /**
     * Redimensiona (lado máx. 1600 px), corrige orientación EXIF y codifica a WebP.
     * Devuelve null si GD/WebP no está disponible o la imagen no se puede procesar.
     */
    private function convertirAWebp(UploadedFile $archivo): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $info = @getimagesize($archivo->getRealPath());

        if (! $info || $info[0] * $info[1] > self::MAX_PIXELES) {
            return null;
        }

        $imagen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));

        if ($imagen === false) {
            return null;
        }

        $imagen = $this->orientar($imagen, $archivo, (int) $info[2]);

        if (! imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }

        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));

        if ($escala < 1) {
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto = max(1, (int) round($alto * $escala));
            $redimensionada = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            imagealphablending($redimensionada, false);
            imagesavealpha($redimensionada, true);
            imagecopyresampled($redimensionada, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
            $imagen = $redimensionada;
        }

        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);

        ob_start();
        $ok = imagewebp($imagen, null, self::CALIDAD_WEBP);
        $datos = (string) ob_get_clean();

        return $ok && $datos !== '' ? $datos : null;
    }

    private function orientar(\GdImage $imagen, UploadedFile $archivo, int $tipo): \GdImage
    {
        if ($tipo !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $imagen;
        }

        $exif = @exif_read_data($archivo->getRealPath());
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($grados === 0) {
            return $imagen;
        }

        $rotada = imagerotate($imagen, $grados, 0);

        if ($rotada === false) {
            return $imagen;
        }

        return $rotada;
    }

    private function borrarArchivo(?string $ruta): void
    {
        if ($ruta && ! Str::startsWith($ruta, ['http://', 'https://']) && Storage::disk(self::DISCO)->exists($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }
}
