<?php

namespace App\Exceptions;

use Illuminate\Contracts\Validation\Validator;

/**
 * Validation Exception
 * 
 * Exception thrown when validation fails.
 * Provides structured validation error information.
 */
class ValidationException extends BaseException
{
    protected array $errors;

    /**
     * Create a new validation exception instance.
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        $this->errors = $errors;
        parent::__construct($message, 422);
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create from Laravel validator.
     */
    public static function fromValidator(Validator $validator, string $message = 'Validation failed'): self
    {
        return new self($validator->errors()->toArray(), $message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], 422);
    }
}
