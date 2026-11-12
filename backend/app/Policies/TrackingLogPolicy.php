<?php

namespace App\Policies;

use App\Models\TrackingLog;
use App\Models\User;

class TrackingLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tracking.view');
    }

    public function view(User $user, TrackingLog $trackingLog): bool
    {
        return $user->can('tracking.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tracking.create');
    }

    public function update(User $user, TrackingLog $trackingLog): bool
    {
        return $user->can('tracking.update');
    }

    public function delete(User $user, TrackingLog $trackingLog): bool
    {
        return $user->can('tracking.delete');
    }
}
