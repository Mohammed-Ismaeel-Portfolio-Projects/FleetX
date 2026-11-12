<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    /**
     * Determine whether the user can view any assignments.
     */
    public function viewAny(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('assignment.view');
        }

        if ($user->isPartnerCarrierUser()) {
            return $user->can('assignment.view');
        }

        return false;
    }

    /**
     * Determine whether the user can view the assignment.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('assignment.view');
        }

        if ($user->isPartnerCarrierUser()) {
            $assignment->loadMissing('shipment');
            $shipment = $assignment->shipment;

            return $shipment !== null
                && $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        if ($user->role?->name === 'driver') {
            $user->loadMissing('driver');
            $linkedDriverId = $user->driver?->id;

            return $linkedDriverId !== null && (int) $assignment->driver_id === (int) $linkedDriverId;
        }

        return false;
    }

    /**
     * Determine whether the user can create assignments.
     */
    public function create(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('assignment.create');
        }

        if ($user->isPartnerCarrierUser()) {
            return $user->can('assignment.create');
        }

        return false;
    }

    /**
     * Determine whether the user can update the assignment.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('assignment.update');
        }

        if ($user->isPartnerCarrierUser()) {
            $assignment->loadMissing('shipment');
            $shipment = $assignment->shipment;

            return $shipment !== null
                && $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the assignment.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('assignment.delete');
    }

    /**
     * Determine whether the user can restore the assignment.
     */
    public function restore(User $user): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('assignment.restore');
    }

    /**
     * Determine whether the user can permanently delete the assignment.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('assignment.force-delete');
    }

    /**
     * Determine whether the user can change assignment status.
     */
    public function changeStatus(User $user, Assignment $assignment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('assignment.change-status');
        }

        if ($user->isPartnerCarrierUser()) {
            $assignment->loadMissing('shipment');
            $shipment = $assignment->shipment;

            return $shipment !== null
                && $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        return false;
    }
}
