<?php

namespace App\Exceptions;

/**
 * Business Exception
 * 
 * Exception thrown when a business rule is violated.
 * Used for domain-specific validation errors.
 */
class BusinessException extends BaseException
{
    /**
     * Create a new business exception instance.
     */
    public function __construct(string $message = 'Business rule violated', int $code = 400, array $context = [])
    {
        parent::__construct($message, $code, null, $context);
    }
}
