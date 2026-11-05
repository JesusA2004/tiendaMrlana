<?php

namespace App\Services\Payments;

use App\Services\Payments\Exceptions\OnlinePaymentsDisabledException;
use App\Services\Payments\Gateways\OpenpayGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        if (! config('payments.enabled', false)) {
            throw new OnlinePaymentsDisabledException;
        }

        $gateway = $name ?: config('payments.default');

        return match ($gateway) {
            'openpay' => app(OpenpayGateway::class),

            default => throw new InvalidArgumentException("La pasarela de pago [{$gateway}] no está soportada."),
        };
    }
}
