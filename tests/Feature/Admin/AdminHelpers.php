<?php

/*
| Helpers de pruebas del panel de operación (nombres con prefijo `op` para no chocar con otros archivos).
*/

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Http\UploadedFile;

function opComprobantePdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('comprobante.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

/** Pedido por transferencia con comprobante en revisión. */
function opPedidoEnRevision(User $cliente, Producto $producto): Pedido
{
    $pedido = crearPedidoCheckout($cliente, [[$producto, 1]]);
    app(PedidoWorkflow::class)->registrarComprobante($pedido, $cliente, opComprobantePdf(), 'REF-OP-1', now()->toDateString(), (float) $pedido->total);

    return $pedido->fresh();
}

function opPagoActual(Pedido $pedido): Pago
{
    return $pedido->pagos()->latest('id')->firstOrFail();
}

/** Pedido con pago aprobado y listo para la logística. */
function opPedidoAprobado(User $cliente, Producto $producto, User $admin): Pedido
{
    $pedido = opPedidoEnRevision($cliente, $producto);
    app(PedidoWorkflow::class)->aprobarPago(opPagoActual($pedido), $admin);

    return $pedido->fresh();
}
