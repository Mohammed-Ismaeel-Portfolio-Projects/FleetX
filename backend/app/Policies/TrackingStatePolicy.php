<?php

namespace App\Policies;

use App\Models\TrackingState;
use App\Models\User;

class TrackingStatePolicy
{
    /**
     * Determine whether the user can view any tracking states.
     */
    public function viewAny(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if (! $user->isFleetXInternal()) {
            return false;
        }

        return in_array($user->role?->name, ['admin', 'dispatcher'], true);
    }

    /**
     * Determine whether the user can view a tracking state.
     */
    public function view(User $user, TrackingState $trackingState): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return in_array($user->role?->name, ['admin', 'dispatcher'], true);
        }

        if ($user->isPartnerCarrierUser()) {
            $trackingState->loadMissing('shipment');
            $shipment = $trackingState->shipment;

            return $shipment !== null
                && $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create tracking states.
     */
    public function create(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if (! $user->isFleetXInternal()) {
            return false;
        }

        return in_array($user->role?->name, ['admin', 'dispatcher'], true);
    }

    /**
     * Determine whether the user can update tracking states.
     */
    public function update(User $user, TrackingState $trackingState): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->role?->name === 'admin';
    }

    /**
     * Determine whether the user can delete tracking states.
     */
    public function delete(User $user, TrackingState $trackingState): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->role?->name === 'admin';
    }
}
