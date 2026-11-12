<?php

namespace App\Exceptions;

use Exception;

/**
 * Base Exception
 * 
 * Base exception class for all custom exceptions in the application.
 * Provides common properties and methods for exception handling.
 */
class BaseException extends Exception
{
    protected array $context = [];

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, array $context = [])
    {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the exception context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ], $this->getCode() ?: 500);
    }
}
