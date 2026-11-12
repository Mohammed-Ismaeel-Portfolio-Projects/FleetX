<?php

namespace App\Actions\Auth;

use App\Core\ApiResult;
use App\Models\User;

/**
 * Resolve the authenticated user's domain identity (Driver or Company).
 * Honest nulls when unlinked — never fabricates IDs.
 */
class ResolveAuthIdentityAction
{
    public function execute(User $user): ApiResult
    {
        $user->loadMissing(['role', 'driver', 'company']);

        $role = $user->role?->name;

        $payload = [
            'user_id' => $user->id,
            'role' => $role,
            'driver' => null,
            'company' => null,
            'linked' => false,
            'unlink_reason' => null,
        ];

        if ($role === 'driver') {
            if ($user->driver) {
                $payload['driver'] = [
                    'id' => $user->driver->id,
                    'employee_number' => $user->driver->employee_number,
                ];
                $payload['linked'] = true;
            } else {
                $payload['unlink_reason'] = 'Driver user is not linked to a Driver record';
            }

            return ApiResult::success($payload, 'Identity resolved');
        }

        if ($role === 'client') {
            if ($user->company) {
                $payload['company'] = [
                    'id' => $user->company->id,
                    'company_code' => $user->company->company_code,
                    'company_name' => $user->company->company_name,
                ];
                $payload['linked'] = true;
            } else {
                $payload['unlink_reason'] = 'Client user is not linked to a Company record';
            }

            return ApiResult::success($payload, 'Identity resolved');
        }

        // admin / dispatcher — no Driver/Company participant identity required
        $payload['unlink_reason'] = 'Role does not require Driver or Company identity linkage';

        return ApiResult::success($payload, 'Identity resolved');
    }
}
