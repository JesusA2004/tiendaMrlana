<?php

namespace App\Services\Payments\Data;

class PaymentChargeData
{
    public function __construct(
        public readonly string $tokenId,
        public readonly string $deviceSessionId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly ?string $customerPhone = null,
    ) {}

}
