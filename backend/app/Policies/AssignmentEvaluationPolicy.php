<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssignmentEvaluation;
use App\Models\User;

/**
 * DRIVER-EVAL-02 authorization boundaries.
 *
 * Admin / Dispatcher: fleet operational visibility
 * Driver: own evaluations only
 * Client: denied
 */
class AssignmentEvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('driverEvaluation.viewAny');
    }

    public function view(User $user, AssignmentEvaluation $evaluation): bool
    {
        if ($user->can('driverEvaluation.viewAny')) {
            return true;
        }

        if ($user->role?->name !== 'driver') {
            return false;
        }

        $linkedId = $user->driver?->id;

        return $linkedId !== null && (int) $linkedId === (int) $evaluation->driver_id;
    }

    public function manage(User $user): bool
    {
        return $user->can('driverEvaluation.manage');
    }
}
