<?php

namespace App\Repositories;

use App\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Repository
 * 
 * Base class for all repositories.
 * Provides standard CRUD operations using Eloquent.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * Create a new repository instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Find a record by ID.
     */
    public function find(string|int $id): ?object
    {
        return $this->model->find($id);
    }

    /**
     * Find a record by ID or throw exception.
     */
    public function findOrFail(string|int $id): object
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Get all records.
     */
    public function all(): array
    {
        return $this->model->all()->toArray();
    }

    /**
     * Create a new record.
     */
    public function create(array $data): object
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     */
    public function update(string|int $id, array $data): bool
    {
        $model = $this->findOrFail($id);
        
        return $model->update($data);
    }

    /**
     * Delete a record.
     */
    public function delete(string|int $id): bool
    {
        $model = $this->findOrFail($id);
        
        return $model->delete();
    }

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15): object
    {
        return $this->model->paginate($perPage);
    }

    /**
     * Find records by criteria.
     */
    public function findByCriteria(array $criteria): array
    {
        $query = $this->model->query();

        foreach ($criteria as $key => $value) {
            $query->where($key, $value);
        }

        return $query->get()->toArray();
    }

    /**
     * Count records.
     */
    public function count(): int
    {
        return $this->model->count();
    }
}
