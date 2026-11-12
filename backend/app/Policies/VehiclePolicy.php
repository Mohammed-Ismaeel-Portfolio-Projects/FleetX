<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function view(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('vehicle.view');
        }

        if ($user->isPartnerCarrierUser()) {
            return $user->can('vehicle.view');
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.create');
    }

    public function show(User $user, Vehicle $vehicle): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('vehicle.view');
        }

        if ($user->isPartnerCarrierUser()) {
            return $vehicle->company_id !== null && $vehicle->company_id === $user->company_id;
        }

        return false;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.delete');
    }

    public function restore(User $user): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.restore');
    }

    public function toggleStatus(User $user, Vehicle $vehicle): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.toggle-status');
    }

    public function pairDriver(User $user, Vehicle $vehicle): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.pair-driver');
    }

    public function unpairDriver(User $user, Vehicle $vehicle): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('vehicle.unpair-driver');
    }
}
