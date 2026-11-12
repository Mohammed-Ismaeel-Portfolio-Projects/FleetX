<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Base Policy
 * 
 * Base class for all authorization policies.
 * Provides common authorization logic.
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view any records.
     */
    public function viewAny($user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the model.
     */
    public function view($user, $model): bool
    {
        return true;
    }

    /**
     * Determine if the user can create models.
     */
    public function create($user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the model.
     */
    public function update($user, $model): bool
    {
        return true;
    }

    /**
     * Determine if the user can delete the model.
     */
    public function delete($user, $model): bool
    {
        return true;
    }

    /**
     * Determine if the user can restore the model.
     */
    public function restore($user, $model): bool
    {
        return false;
    }

    /**
     * Determine if the user can permanently delete the model.
     */
    public function forceDelete($user, $model): bool
    {
        return false;
    }
}
