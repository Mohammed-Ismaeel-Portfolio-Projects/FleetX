<?php

namespace App\Contracts;

/**
 * Repository Interface
 * 
 * Base interface for all repositories.
 * Defines standard CRUD operations that all repositories must implement.
 */
interface RepositoryInterface
{
    /**
     * Find a record by ID.
     */
    public function find(string|int $id): ?object;

    /**
     * Find a record by ID or throw exception.
     */
    public function findOrFail(string|int $id): object;

    /**
     * Get all records.
     */
    public function all(): array;

    /**
     * Create a new record.
     */
    public function create(array $data): object;

    /**
     * Update a record.
     */
    public update(string|int $id, array $data): bool;

    /**
     * Delete a record.
     */
    public function delete(string|int $id): bool;

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15): object;

    /**
     * Find records by criteria.
     */
    public function findByCriteria(array $criteria): array;

    /**
     * Count records.
     */
    public function count(): int;
}
