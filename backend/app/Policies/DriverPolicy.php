<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Driver;

class DriverPolicy
{
    /**
     * List drivers — dispatcher/admin only (via driver.view gate).
     */
    public function view(User $user): bool
    {
        return $user->can('driver.view');
    }

    public function create(User $user): bool
    {
        return $user->can('driver.create');
    }

    /**
     * View one driver — ops roles via gate, or linked driver user for own record only.
     */
    public function show(User $user, Driver $driver): bool
    {
        if ($user->can('driver.view')) {
            return true;
        }

        if ($user->role?->name !== 'driver') {
            return false;
        }

        $linkedId = $user->driver?->id;

        return $linkedId !== null && (int) $linkedId === (int) $driver->id;
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->can('driver.update');
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->can('driver.delete');
    }

    public function restore(User $user): bool
    {
        return $user->can('driver.restore');
    }

    public function toggleStatus(User $user, Driver $driver): bool
    {
        return $user->can('driver.toggle-status');
    }
}
