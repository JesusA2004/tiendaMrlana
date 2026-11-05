<?php

namespace App\Http\Controllers\Cliente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Compra\SubirComprobanteRequest;
use App\Models\Pago;
use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprobantes de transferencia: subida (dueño) y descarga protegida (dueño o staff).
 * Los archivos viven en el disco privado; nunca se exponen rutas reales.
 */
class ComprobanteController extends Controller
{
    public function __construct(private readonly PedidoWorkflow $workflow) {}

    public function store(SubirComprobanteRequest $request, Pedido $pedido): RedirectResponse
    {
        $data = $request->validated();

        // Reglas de negocio (estado del pago, envío por cotizar, etc.): DominioException → flash error.
        $this->workflow->registrarComprobante(
            $pedido,
            $request->user(),
            $request->file('archivo'),
            filled($data['referencia'] ?? null) ? trim($data['referencia']) : null,
            $data['fecha_transferencia'] ?? null,
            isset($data['monto_declarado']) ? round((float) $data['monto_declarado'], 2) : null,
        );

        return back()->with('success', 'Recibimos tu comprobante. Lo revisaremos y te avisaremos por correo.');
    }

    public function descargar(Pedido $pedido, PagoComprobante $comprobante): StreamedResponse
    {
        Gate::authorize('descargarComprobante', $pedido);

        $pertenece = Pago::query()
            ->whereKey($comprobante->pago_id)
            ->where('pedido_id', $pedido->id)
            ->exists();

        abort_unless($pertenece, 404);

        $disco = Storage::disk($comprobante->disk ?: 'local');
        abort_unless($disco->exists($comprobante->ruta), 404);

        $extension = strtolower(pathinfo((string) $comprobante->ruta, PATHINFO_EXTENSION)) ?: 'bin';
        $nombre = 'comprobante-'.Str::slug((string) $pedido->folio).'-'.$comprobante->id.'.'.$extension;

        return $disco->download($comprobante->ruta, $nombre, [
            'Content-Type' => $comprobante->mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
