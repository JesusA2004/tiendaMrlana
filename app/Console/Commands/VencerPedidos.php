<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Services\Pedidos\PedidoWorkflow;
use Illuminate\Console\Command;
use Throwable;

/**
 * Vence pedidos pendientes de pago cuyo plazo terminó (programado cada 15 min en routes/console.php).
 */
class VencerPedidos extends Command
{
    protected $signature = 'pedidos:vencer';

    protected $description = 'Marca como vencidos los pedidos sin pago cuyo plazo expiró y libera su stock';

    public function handle(PedidoWorkflow $workflow): int
    {
        $vencidos = 0;

        Pedido::query()->vencibles()->orderBy('id')->chunkById(100, function ($pedidos) use ($workflow, &$vencidos) {
            foreach ($pedidos as $pedido) {
                try {
                    if ($workflow->vencer($pedido)) {
                        $vencidos++;
                    }
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("No se pudo vencer el pedido {$pedido->folio}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Pedidos vencidos: {$vencidos}.");

        return self::SUCCESS;
    }
}
