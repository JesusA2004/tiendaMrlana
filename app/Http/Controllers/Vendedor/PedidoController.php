<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;

/**
 * Compatibilidad: el vendedor ahora usa el panel /admin/pedidos con acciones limitadas
 * por gate. Las rutas vendedor.* sólo redirigen (enlaces y marcadores viejos).
 */
class PedidoController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.pedidos.index', status: 301);
    }

    public function show(Pedido $pedido): RedirectResponse
    {
        return redirect()->route('admin.pedidos.show', $pedido, 301);
    }
}
