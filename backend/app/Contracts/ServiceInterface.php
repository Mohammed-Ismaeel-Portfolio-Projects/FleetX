<?php

namespace App\Contracts;

/**
 * Service Interface
 * 
 * Base interface for all services.
 * Defines standard operations that all services must implement.
 */
interface ServiceInterface
{
    /**
     * Get all records.
     */
    public function getAll(): array;

    /**
     * Find a record by ID.
     */
    public function findById(string|int $id): ?object;

    /**
     * Create a new record.
     */
    public function create(array $data): object;

    /**
     * Update a record.
     */
    public function update(string|int $id, array $data): object;

    /**
     * Delete a record.
     */
    public function delete(string|int $id): bool;
}
