<?php

namespace App\Core;

/**
 * API Result Object
 * 
 * Standardized result object for service layer operations.
 * Used to pass operation results between services and controllers.
 */
class ApiResult
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly string $message = '',
        public readonly int $statusCode = 200,
        public readonly mixed $errors = null
    ) {}

    /**
     * Create a success result
     */
    public static function success(mixed $data = null, string $message = 'Success'): self
    {
        return new self(true, $data, $message, 200);
    }

    /**
     * Create an error result
     */
    public static function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null): self
    {
        return new self(false, null, $message, $statusCode, $errors);
    }

    /**
     * Create a not found result
     */
    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self(false, null, $message, 404);
    }

    /**
     * Create a validation error result
     */
    public static function validation(mixed $errors, string $message = 'Validation failed'): self
    {
        return new self(false, null, $message, 422, $errors);
    }

    /**
     * Convert to JSON response
     */
    public function toResponse(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::error($this->message, $this->statusCode, $this->errors);
    }
}
