<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Acceso a un pedido desde el lado del cliente (gracias, mis pedidos, PDF, comprobantes).
 * Staff con operar-pedidos o revisar-pagos puede consultar cualquier pedido.
 */
class PedidoPolicy
{
    public function view(User $user, Pedido $pedido): bool
    {
        return $this->esDueno($user, $pedido)
            || Gate::forUser($user)->any(['operar-pedidos', 'revisar-pagos']);
    }

    /** Sólo el dueño sube comprobantes (el workflow vuelve a validarlo). */
    public function subirComprobante(User $user, Pedido $pedido): bool
    {
        return $this->esDueno($user, $pedido);
    }

    public function descargarComprobante(User $user, Pedido $pedido): bool
    {
        return $this->view($user, $pedido);
    }

    private function esDueno(User $user, Pedido $pedido): bool
    {
        return $pedido->user_id !== null && (int) $pedido->user_id === (int) $user->id;
    }
}
