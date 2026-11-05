<?php

namespace App\Services\Inventario;

use App\Enums\ModalidadVenta;
use App\Enums\TipoMovimientoInventario;
use App\Exceptions\DominioException;
use App\Exceptions\StockInsuficienteException;
use App\Models\InventarioMovimiento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ÚNICO punto que modifica `productos.stock`. Cada cambio deja un InventarioMovimiento
 * y se hace con lockForUpdate dentro de una transacción.
 */
class InventarioService
{
    public function entrada(Producto|int $producto, int $cantidad, ?User $user = null, ?string $motivo = null): InventarioMovimiento
    {
        $this->exigirPositivo($cantidad);

        return $this->mover($producto, $cantidad, TipoMovimientoInventario::Entrada, $user, $motivo);
    }

    public function salida(Producto|int $producto, int $cantidad, ?User $user = null, ?string $motivo = null): InventarioMovimiento
    {
        $this->exigirPositivo($cantidad);

        return $this->mover($producto, -$cantidad, TipoMovimientoInventario::Salida, $user, $motivo);
    }

    public function devolucion(Producto|int $producto, int $cantidad, ?User $user = null, ?string $motivo = null, ?Pedido $pedido = null): InventarioMovimiento
    {
        $this->exigirPositivo($cantidad);

        return $this->mover($producto, $cantidad, TipoMovimientoInventario::Devolucion, $user, $motivo, $pedido);
    }

    /**
     * Fija el stock a un valor absoluto (conteo físico). Registra la diferencia como `ajuste`.
     */
    public function establecer(Producto|int $producto, int $nuevoStock, ?User $user = null, ?string $motivo = null): ?InventarioMovimiento
    {
        if ($nuevoStock < 0) {
            throw new DominioException('Las existencias no pueden ser negativas.');
        }

        return DB::transaction(function () use ($producto, $nuevoStock, $user, $motivo) {
            $bloqueado = $this->bloquear($producto);
            $diferencia = $nuevoStock - (int) $bloqueado->stock;

            if ($diferencia === 0) {
                return null;
            }

            $movimiento = $this->aplicar($bloqueado, $diferencia, TipoMovimientoInventario::Ajuste, $user, $motivo ?? 'Ajuste de existencias');
            $this->sincronizar($producto, $bloqueado);

            return $movimiento;
        });
    }

    /**
     * Ajuste con signo (+/-). No permite dejar stock negativo.
     */
    public function ajustar(Producto|int $producto, int $cantidad, ?User $user = null, ?string $motivo = null): InventarioMovimiento
    {
        if ($cantidad === 0) {
            throw new DominioException('La cantidad del ajuste no puede ser cero.');
        }

        return $this->mover($producto, $cantidad, TipoMovimientoInventario::Ajuste, $user, $motivo);
    }

    /**
     * Reserva (descuenta) existencias de los items de inventario del pedido. Bajo pedido no descuenta.
     * Bloquea productos en orden de id para evitar interbloqueos.
     *
     * @throws StockInsuficienteException
     */
    public function reservarParaPedido(Pedido $pedido, ?User $user = null): void
    {
        DB::transaction(function () use ($pedido, $user) {
            if ($pedido->stock_reservado) {
                return;
            }

            $cantidades = $pedido->items()
                ->whereNotNull('producto_id')
                ->get()
                ->groupBy('producto_id')
                ->map(fn ($items) => (int) $items->sum('cantidad'))
                ->sortKeys();

            $productos = Producto::query()
                ->whereIn('id', $cantidades->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cantidades as $productoId => $cantidad) {
                $producto = $productos->get($productoId);

                if (! $producto || $producto->modalidad_venta === ModalidadVenta::BajoPedido->value) {
                    continue;
                }

                if ((int) $producto->stock < $cantidad) {
                    throw new StockInsuficienteException($producto->id, $producto->nombre, max(0, (int) $producto->stock), $cantidad);
                }

                $this->aplicar($producto, -$cantidad, TipoMovimientoInventario::Venta, $user, 'Reserva por pedido '.$pedido->folio, $pedido);
            }

            $pedido->forceFill(['stock_reservado' => true])->save();
        });
    }

    /**
     * Regresa al inventario lo reservado por el pedido (neto de liberaciones previas).
     */
    public function liberarDePedido(Pedido $pedido, ?User $user = null, ?string $motivo = null): void
    {
        DB::transaction(function () use ($pedido, $user, $motivo) {
            if (! $pedido->stock_reservado) {
                return;
            }

            $netos = InventarioMovimiento::query()
                ->where('pedido_id', $pedido->id)
                ->whereIn('tipo', [TipoMovimientoInventario::Venta->value, TipoMovimientoInventario::Liberacion->value])
                ->get()
                ->groupBy('producto_id')
                ->map(fn ($movs) => -(int) $movs->sum('cantidad'))
                ->filter(fn (int $neto) => $neto > 0)
                ->sortKeys();

            if ($netos->isNotEmpty()) {
                $productos = Producto::query()
                    ->whereIn('id', $netos->keys())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($netos as $productoId => $cantidad) {
                    if ($producto = $productos->get($productoId)) {
                        $this->aplicar($producto, $cantidad, TipoMovimientoInventario::Liberacion, $user, $motivo ?? 'Liberación por pedido '.$pedido->folio, $pedido);
                    }
                }
            }

            $pedido->forceFill(['stock_reservado' => false])->save();
        });
    }

    private function mover(Producto|int $producto, int $cantidad, TipoMovimientoInventario $tipo, ?User $user, ?string $motivo, ?Pedido $pedido = null): InventarioMovimiento
    {
        return DB::transaction(function () use ($producto, $cantidad, $tipo, $user, $motivo, $pedido) {
            $bloqueado = $this->bloquear($producto);

            if ((int) $bloqueado->stock + $cantidad < 0) {
                throw new StockInsuficienteException($bloqueado->id, $bloqueado->nombre, max(0, (int) $bloqueado->stock), abs($cantidad));
            }

            $movimiento = $this->aplicar($bloqueado, $cantidad, $tipo, $user, $motivo, $pedido);
            $this->sincronizar($producto, $bloqueado);

            return $movimiento;
        });
    }

    private function aplicar(Producto $producto, int $cantidad, TipoMovimientoInventario $tipo, ?User $user, ?string $motivo, ?Pedido $pedido = null): InventarioMovimiento
    {
        $anterior = (int) $producto->stock;
        $nuevo = $anterior + $cantidad;

        $producto->forceFill(['stock' => $nuevo])->save();

        return InventarioMovimiento::create([
            'producto_id' => $producto->id,
            'user_id' => $user?->id,
            'tipo' => $tipo->value,
            'cantidad' => $cantidad,
            'stock_anterior' => $anterior,
            'stock_nuevo' => $nuevo,
            'pedido_id' => $pedido?->id,
            'motivo' => $motivo !== null ? mb_substr($motivo, 0, 255) : null,
        ]);
    }

    private function bloquear(Producto|int $producto): Producto
    {
        $id = $producto instanceof Producto ? $producto->id : $producto;

        return Producto::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function sincronizar(Producto|int $original, Producto $bloqueado): void
    {
        if ($original instanceof Producto) {
            $original->setRawAttributes($bloqueado->getAttributes(), true);
        }
    }

    private function exigirPositivo(int $cantidad): void
    {
        if ($cantidad < 1) {
            throw new DominioException('La cantidad debe ser mayor a cero.');
        }
    }
}
