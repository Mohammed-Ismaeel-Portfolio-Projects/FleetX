<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    /**
     * Determine whether the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('shipment.view');
        }

        if ($user->isPartnerCarrierUser()) {
            return $user->can('shipment.view');
        }

        return false;
    }

    /**
     * Determine whether the user can view the shipment.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('shipment.view');
        }

        if ($user->isPartnerCarrierUser()) {
            $isExecutingPartner = $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
            $isCustomerPartner = $shipment->customer_company_id === $user->company_id;

            return $isExecutingPartner || $isCustomerPartner;
        }

        return false;
    }

    /**
     * Determine whether the user can create shipments.
     */
    public function create(User $user): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('shipment.create');
    }

    /**
     * Determine whether the user can update the shipment.
     */
    public function update(User $user, Shipment $shipment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('shipment.update');
        }

        if ($user->isPartnerCarrierUser()) {
            return $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the shipment.
     */
    public function delete(User $user, Shipment $shipment): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('shipment.delete');
    }

    /**
     * Determine whether the user can restore the shipment.
     */
    public function restore(User $user): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('shipment.restore');
    }

    /**
     * Determine whether the user can permanently delete the shipment.
     */
    public function forceDelete(User $user, Shipment $shipment): bool
    {
        if (! $user->isFleetXInternal()) {
            return false;
        }

        return $user->can('shipment.force-delete');
    }

    /**
     * Determine whether the user can change shipment status.
     */
    public function changeStatus(User $user, Shipment $shipment): bool
    {
        if ($user->company_id !== null && $user->company?->is_active === false) {
            return false;
        }

        if ($user->isFleetXInternal()) {
            return $user->can('shipment.change-status');
        }

        if ($user->isPartnerCarrierUser()) {
            return $shipment->executing_company_id !== null
                && $shipment->executing_company_id === $user->company_id;
        }

        return false;
    }
}
