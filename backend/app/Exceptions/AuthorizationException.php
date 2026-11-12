<?php

namespace App\Exceptions;

/**
 * Authorization Exception
 * 
 * Exception thrown when a user is not authorized to perform an action.
 */
class AuthorizationException extends BaseException
{
    /**
     * Create a new authorization exception instance.
     */
    public function __construct(string $message = 'Unauthorized', array $context = [])
    {
        parent::__construct($message, 403, null, $context);
    }
}
