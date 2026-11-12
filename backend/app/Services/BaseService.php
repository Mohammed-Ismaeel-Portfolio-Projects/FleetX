<?php

namespace App\Services;

use App\Contracts\ServiceInterface;
use App\Contracts\RepositoryInterface;
use App\Exceptions\NotFoundException;

/**
 * Base Service
 * 
 * Base class for all services.
 * Services orchestrate business logic and coordinate repositories.
 */
abstract class BaseService implements ServiceInterface
{
    /**
     * The repository instance.
     */
    protected RepositoryInterface $repository;

    /**
     * Create a new service instance.
     */
    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all records.
     */
    public function getAll(): array
    {
        return $this->repository->all();
    }

    /**
     * Find a record by ID.
     */
    public function findById(string|int $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * Find a record by ID or throw exception.
     */
    protected function findOrFail(string|int $id): object
    {
        $model = $this->repository->find($id);

        if (!$model) {
            throw new NotFoundException();
        }

        return $model;
    }

    /**
     * Create a new record.
     */
    public function create(array $data): object
    {
        return $this->repository->create($data);
    }

    /**
     * Update a record.
     */
    public function update(string|int $id, array $data): object
    {
        $this->findOrFail($id);
        
        $this->repository->update($id, $data);
        
        return $this->findOrFail($id);
    }

    /**
     * Delete a record.
     */
    public function delete(string|int $id): bool
    {
        $this->findOrFail($id);
        
        return $this->repository->delete($id);
    }
}
