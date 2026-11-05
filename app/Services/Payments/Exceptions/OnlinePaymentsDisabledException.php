<?php

namespace App\Services\Payments\Exceptions;

use App\Exceptions\DominioException;

/**
 * Los pagos en línea (Openpay/tarjeta) están deshabilitados: config('payments.enabled') = false.
 */
class OnlinePaymentsDisabledException extends DominioException
{
    public function __construct(string $message = 'Los pagos en línea con tarjeta no están disponibles.')
    {
        parent::__construct($message);
    }
}
