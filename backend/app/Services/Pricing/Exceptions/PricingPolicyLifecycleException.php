<?php

declare(strict_types=1);

namespace App\Services\Pricing\Exceptions;

use Exception;

class PricingPolicyLifecycleException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }
}
