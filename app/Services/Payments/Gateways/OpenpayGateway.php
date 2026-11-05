<?php

namespace App\Services\Payments\Gateways;

use App\Models\Pago;
use App\Services\Payments\Data\PaymentChargeData;
use App\Services\Payments\Data\PaymentResultData;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenpayGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'openpay';
    }

    public function charge(Pago $pago, PaymentChargeData $data): PaymentResultData
    {
        $merchantId = config('payments.gateways.openpay.merchant_id');
        $privateKey = config('payments.gateways.openpay.private_key');

        if (! $merchantId || ! $privateKey) {
            return PaymentResultData::failed(
                status: 'error',
                message: 'Openpay no está configurado correctamente.'
            );
        }

        $payload = [
            'method' => 'card',
            'amount' => round($data->amount, 2),
            'currency' => $data->currency,
            'source_id' => $data->tokenId,
            'description' => $data->description,
            'device_session_id' => $data->deviceSessionId,
            'customer' => [
                'name' => $data->customerName,
                'email' => $data->customerEmail,
                'phone_number' => $data->customerPhone,
            ],
        ];

        try {
            $response = Http::withBasicAuth($privateKey, '')
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post($this->chargesUrl($merchantId), $payload);

            $body = $response->json();

            if (! is_array($body)) {
                $body = [
                    'raw' => $response->body(),
                ];
            }

            if (! $response->successful()) {
                return PaymentResultData::failed(
                    status: 'rechazado',
                    rawResponse: $body,
                    message: $body['description'] ?? $body['message'] ?? 'El pago fue rechazado por Openpay.'
                );
            }

            $openpayStatus = (string) ($body['status'] ?? 'completed');

            $status = match ($openpayStatus) {
                'completed' => 'aprobado',
                'in_progress' => 'procesando',
                'failed' => 'rechazado',
                'cancelled' => 'cancelado',
                default => 'procesando',
            };

            return PaymentResultData::success(
                status: $status,
                externalReference: $body['id'] ?? null,
                authorization: $body['authorization'] ?? null,
                rawResponse: $body,
                message: 'Respuesta recibida correctamente desde Openpay.'
            );
        } catch (Throwable $exception) {
            report($exception);

            return PaymentResultData::failed(
                status: 'error',
                rawResponse: [
                    'exception' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
                message: 'No fue posible conectar con Openpay: '.$exception->getMessage()
            );
        }
    }

    private function chargesUrl(string $merchantId): string
    {
        $baseUrl = config('payments.gateways.openpay.sandbox')
            ? config('payments.gateways.openpay.sandbox_base_url')
            : config('payments.gateways.openpay.production_base_url');

        return rtrim($baseUrl, '/').'/v1/'.$merchantId.'/charges';
    }
}
