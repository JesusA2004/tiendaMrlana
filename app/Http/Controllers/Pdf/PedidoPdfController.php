<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Cliente\Concerns\PresentaPedido;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Pdf\PdfNoDisponibleException;
use App\Services\Pdf\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDFs del pedido:
 * - cliente.pedidos.pdf      → "Resumen de pedido" para el dueño (incluye instrucciones de pago).
 * - admin.pedidos.pdf        → "Resumen de pedido" para staff (sin datos bancarios).
 * - admin.pedidos.hoja-empaque → "Hoja de empaque" (staff).
 * Si Chrome/Node no están disponibles: redirect back con flash `error` (nunca 500).
 */
class PedidoPdfController extends Controller
{
    use PresentaPedido;

    public function __construct(
        private readonly PdfService $pdf,
        private readonly ConfiguracionTienda $config,
    ) {}

    public function cliente(Request $request, Pedido $pedido): Response|RedirectResponse
    {
        abort_unless((int) $pedido->user_id === (int) $request->user()->id, 404);

        return $this->resumen($pedido, esDueno: true);
    }

    public function admin(Request $request, Pedido $pedido): Response|RedirectResponse
    {
        Gate::authorize('operar-pedidos');

        return $this->resumen($pedido, esDueno: (int) $pedido->user_id === (int) $request->user()->id);
    }

    public function hojaEmpaque(Pedido $pedido): Response|RedirectResponse
    {
        Gate::authorize('operar-pedidos');

        return $this->generar('pdf.hoja-empaque', [
            'pedido' => $this->presentarPedido($pedido, incluirComprobantes: false),
            'tienda' => $this->datosTienda(),
            'generado_en' => now(),
        ], 'hoja-empaque-'.$pedido->folio);
    }

    private function resumen(Pedido $pedido, bool $esDueno): Response|RedirectResponse
    {
        return $this->generar('pdf.resumen-pedido', [
            'pedido' => $this->presentarPedido($pedido, incluirComprobantes: false),
            'instrucciones' => $this->instruccionesPago($pedido, $esDueno, $this->config),
            'tienda' => $this->datosTienda(),
            'generado_en' => now(),
        ], 'pedido-'.$pedido->folio);
    }

    /** @param  array<string, mixed>  $data */
    private function generar(string $vista, array $data, string $nombre): Response|RedirectResponse
    {
        try {
            $contenido = $this->pdf->desdeVista($vista, $data);
        } catch (PdfNoDisponibleException $e) {
            return back()->with('error', $e->getMessage());
        }

        $archivo = Str::slug($nombre).'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$archivo.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function datosTienda(): array
    {
        return [
            'nombre' => $this->config->nombre(),
            'eslogan' => $this->config->string('tienda.eslogan'),
            'contacto' => $this->config->contacto(),
            'logo' => PdfService::logoDataUri(),
        ];
    }
}
