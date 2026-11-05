<?php

namespace App\Services\Payments;

use App\Mail\PedidoEstadoMail;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoEstatusHistorial;
use App\Models\TransaccionPasarela;
use App\Services\Payments\Data\PaymentChargeData;
use App\Services\Payments\Data\PaymentResultData;
use App\Services\Payments\Exceptions\OnlinePaymentsDisabledException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * @throws OnlinePaymentsDisabledException mientras config('payments.enabled') sea false
     */
    public function charge(Pedido $pedido, Pago $pago, PaymentChargeData $data): PaymentResultData
    {
        if (! config('payments.enabled', false)) {
            throw new OnlinePaymentsDisabledException;
        }

        $gateway = $this->gatewayManager->gateway();

        $pago->update([
            'estatus' => 'procesando',
        ]);

        TransaccionPasarela::create([
            'pago_id' => $pago->id,
            'pasarela' => $gateway->name(),
            'tipo' => 'cargo_intento',
            'transaccion_id' => null,
            'payload' => [
                'amount' => $data->amount,
                'currency' => $data->currency,
                'description' => $data->description,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
            ],
            'respuesta' => null,
        ]);

        $result = $gateway->charge($pago, $data);

        DB::transaction(function () use ($pedido, $pago, $gateway, $result) {
            $pago->update([
                'estatus' => $result->status,
                'referencia_externa' => $result->externalReference,
                'autorizacion' => $result->authorization,
                'respuesta_pasarela' => $result->rawResponse,
                'pagado_en' => $result->successful && $result->status === 'aprobado'
                    ? now()
                    : null,
            ]);

            TransaccionPasarela::create([
                'pago_id' => $pago->id,
                'pasarela' => $gateway->name(),
                'tipo' => 'cargo_respuesta',
                'transaccion_id' => $result->externalReference,
                'payload' => null,
                'respuesta' => $result->rawResponse,
            ]);

            if ($result->successful && $result->status === 'aprobado') {
                $pedido->update([
                    'estatus' => 'pagado',
                    'pagado_en' => now(),
                ]);

                PedidoEstatusHistorial::create([
                    'pedido_id' => $pedido->id,
                    'user_id' => auth()->id(),
                    'estatus' => 'pagado',
                    'comentario' => 'Pago aprobado por '.$gateway->name().'.',
                ]);

                if ($pedido->correo_cliente) {
                    try {
                        Mail::to($pedido->correo_cliente)->send(new PedidoEstadoMail($pedido->fresh(['items', 'direccion', 'pagos.metodoPago']), 'pagado'));
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }
        });

        return $result;
    }
}
