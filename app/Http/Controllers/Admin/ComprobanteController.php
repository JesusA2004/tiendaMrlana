<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PagoComprobante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprobantes de pago guardados en disco privado. Sólo personal con revisar-pagos u
 * operar-pedidos; nunca se exponen rutas de disco ni URLs públicas.
 */
class ComprobanteController extends Controller
{
    public function show(Request $request, PagoComprobante $comprobante): StreamedResponse
    {
        return $this->responder($request, $comprobante, 'inline');
    }

    public function descargar(Request $request, PagoComprobante $comprobante): StreamedResponse
    {
        return $this->responder($request, $comprobante, 'attachment');
    }

    private function responder(Request $request, PagoComprobante $comprobante, string $disposicion): StreamedResponse
    {
        abort_unless(Gate::forUser($request->user())->any(['revisar-pagos', 'operar-pedidos']), 403);

        $disco = Storage::disk($comprobante->disk ?: 'local');
        abort_unless($disco->exists($comprobante->ruta), 404, 'El archivo del comprobante no está disponible.');

        $mime = in_array($comprobante->mime, PagoComprobante::MIMES, true) ? $comprobante->mime : 'application/octet-stream';
        $nombre = $this->nombreSeguro($comprobante);

        return $disco->response($comprobante->ruta, $nombre, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            // Sólo se puede incrustar en el propio panel (visor embebido).
            'X-Frame-Options' => 'SAMEORIGIN',
        ], $disposicion);
    }

    private function nombreSeguro(PagoComprobante $comprobante): string
    {
        $extension = strtolower(pathinfo((string) $comprobante->ruta, PATHINFO_EXTENSION)) ?: 'bin';
        $folio = $comprobante->pago?->pedido?->folio ?? 'pedido';

        return 'comprobante-'.preg_replace('/[^A-Za-z0-9_-]/', '', $folio).'-'.$comprobante->id.'.'.$extension;
    }
}
