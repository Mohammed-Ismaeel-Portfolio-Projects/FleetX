<?php

namespace App\DTOs;

/**
 * Base DTO
 * 
 * Base Data Transfer Object for all DTOs.
 * Provides common functionality for data transformation.
 */
abstract class BaseDTO
{
    /**
     * Create DTO from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(...$data);
    }

    /**
     * Convert DTO to array.
     */
    abstract public function toArray(): array;

    /**
     * Validate DTO data.
     */
    public function validate(): bool
    {
        return true;
    }

    /**
     * Get validation errors.
     */
    public function getValidationErrors(): array
    {
        return [];
    }
}
