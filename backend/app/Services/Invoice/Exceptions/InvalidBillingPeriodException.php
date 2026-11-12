<?php

declare(strict_types=1);

namespace App\Services\Invoice\Exceptions;

use RuntimeException;

class InvalidBillingPeriodException extends RuntimeException
{
    public function __construct(
        string $message = 'Billing period end must be after billing period start.',
        public readonly ?string $errorCode = 'invalid_billing_period',
    ) {
        parent::__construct($message);
    }
}
