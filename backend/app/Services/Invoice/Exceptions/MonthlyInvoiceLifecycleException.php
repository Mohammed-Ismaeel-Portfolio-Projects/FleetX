<?php

declare(strict_types=1);

namespace App\Services\Invoice\Exceptions;

use App\Enums\InvoiceLifecycleErrorCode;
use RuntimeException;

class MonthlyInvoiceLifecycleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly ?InvoiceLifecycleErrorCode $errorCode = null,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
