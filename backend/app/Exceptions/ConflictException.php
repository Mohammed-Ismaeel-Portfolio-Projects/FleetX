<?php

namespace App\Exceptions;

/**
 * Conflict Exception
 * 
 * Exception thrown when a resource conflict occurs.
 * Used for concurrent modification conflicts.
 */
class ConflictException extends BaseException
{
    /**
     * Create a new conflict exception instance.
     */
    public function __construct(string $message = 'Resource conflict', array $context = [])
    {
        parent::__construct($message, 409, null, $context);
    }
}
