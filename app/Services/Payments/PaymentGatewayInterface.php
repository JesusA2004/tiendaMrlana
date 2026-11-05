<?php

namespace App\Services\Payments;

use App\Models\Pago;
use App\Services\Payments\Data\PaymentChargeData;
use App\Services\Payments\Data\PaymentResultData;

interface PaymentGatewayInterface
{
    public function charge(Pago $pago, PaymentChargeData $data): PaymentResultData;

    public function name(): string;
}
