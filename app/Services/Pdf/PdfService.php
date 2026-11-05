<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Genera PDFs a partir de vistas Blade con Browsershot (Chrome headless).
 * En pruebas se reemplaza con un fake (app()->instance(PdfService::class, ...)).
 */
class PdfService
{
    /**
     * Renderiza la vista y devuelve el contenido binario del PDF.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PdfNoDisponibleException
     */
    public function desdeVista(string $vista, array $data = []): string
    {
        return $this->desdeHtml(view($vista, $data)->render());
    }

    /**
     * @throws PdfNoDisponibleException
     */
    public function desdeHtml(string $html): string
    {
        try {
            $pdf = $this->browsershot($html)->pdf();
        } catch (Throwable $e) {
            Log::warning('No se pudo generar el PDF con Browsershot.', [
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw new PdfNoDisponibleException(
                'No pudimos generar el PDF en este momento. Inténtalo más tarde o contáctanos.',
                previous: $e,
            );
        }

        if ($pdf === '' || ! str_starts_with($pdf, '%PDF')) {
            throw new PdfNoDisponibleException('No pudimos generar el PDF en este momento. Inténtalo más tarde o contáctanos.');
        }

        return $pdf;
    }

    /** Ruta del logo de marca (PNG negro/blanco) para PDF y correos. */
    public static function logoPath(): ?string
    {
        foreach ([resource_path('js/img/logo.png'), public_path('icons/icon-192.png')] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** Logo embebido como data URI (Chrome renderiza el HTML sin depender de URLs del sitio). */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();

        return $path ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    protected function browsershot(string $html): Browsershot
    {
        $config = config('pdf');

        $shot = Browsershot::html($html)
            ->format((string) ($config['formato'] ?? 'Letter'))
            ->margins(12, 10, 14, 10)
            ->showBackground()
            ->emulateMedia('print')
            ->timeout((int) ($config['timeout'] ?? 60));

        if (filled($config['node_binary'] ?? null)) {
            $shot->setNodeBinary($config['node_binary']);
        }

        if (filled($config['npm_binary'] ?? null)) {
            $shot->setNpmBinary($config['npm_binary']);
        }

        if (filled($config['chrome_path'] ?? null)) {
            $shot->setChromePath($config['chrome_path']);
        }

        if (filled($config['node_modules_path'] ?? null)) {
            $shot->setNodeModulePath($config['node_modules_path']);
        }

        if ($config['no_sandbox'] ?? false) {
            $shot->noSandbox();
        }

        return $shot;
    }
}
