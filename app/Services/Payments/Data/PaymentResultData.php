<?php

namespace App\Services\Payments\Data;

class PaymentResultData
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $status,
        public readonly ?string $externalReference = null,
        public readonly ?string $authorization = null,
        public readonly ?array $rawResponse = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(
        string $status,
        ?string $externalReference = null,
        ?string $authorization = null,
        ?array $rawResponse = null,
        ?string $message = null,
    ): self {
        return new self(
            successful: true,
            status: $status,
            externalReference: $externalReference,
            authorization: $authorization,
            rawResponse: $rawResponse,
            message: $message,
        );
    }

    public static function failed(
        string $status = 'rechazado',
        ?array $rawResponse = null,
        ?string $message = null,
    ): self {
        return new self(
            successful: false,
            status: $status,
            externalReference: null,
            authorization: null,
            rawResponse: $rawResponse,
            message: $message,
        );
    }
}
