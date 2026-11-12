<?php

namespace App\Exceptions;

/**
 * Not Found Exception
 * 
 * Exception thrown when a requested resource is not found.
 */
class NotFoundException extends BaseException
{
    /**
     * Create a new not found exception instance.
     */
    public function __construct(string $message = 'Resource not found', array $context = [])
    {
        parent::__construct($message, 404, null, $context);
    }
}
