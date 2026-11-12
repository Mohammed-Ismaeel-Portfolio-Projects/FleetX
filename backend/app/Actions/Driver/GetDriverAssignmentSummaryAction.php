<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\DriverNotification;
use App\Models\DriverVerificationChallenge;
use App\Models\Issue;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverNotificationCode;

/**
 * Read-only historical assignment summary (D11).
 * Terminal inactive own assignments only. No mutation.
 */
class GetDriverAssignmentSummaryAction
{
    public function execute(User $user, int|string $assignmentId): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        /** @var Assignment|null $assignment */
        $assignment = Assignment::query()
            ->forDriver($driver->id)
            ->with(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer'])
            ->find($assignmentId);

        if ($assignment === null) {
            return ApiResult::notFound('This assignment is no longer available.');
        }

        $statusName = $assignment->status?->name;
        $terminal = DriverAssignmentTransitionMap::isTerminal($statusName) && ! $assignment->is_active;
        if (! $terminal) {
            return ApiResult::error('This assignment is still in progress. Return to Work.', 409, [
                'code' => 'ASSIGNMENT_NOT_HISTORICAL',
            ]);
        }

        $challenges = DriverVerificationChallenge::query()
            ->where('assignment_id', $assignment->id)
            ->whereNotNull('consumed_at')
            ->orderBy('consumed_at')
            ->get(['event', 'consumed_at']);

        $issues = [];
        if ($assignment->shipment_id) {
            $issues = Issue::query()
                ->where('shipment_id', $assignment->shipment_id)
                ->where('driver_id', $driver->id)
                ->with('status')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        $proof = $assignment->shipment?->deliveryProof
            ?? ($assignment->shipment_id
                ? DeliveryProof::query()->where('shipment_id', $assignment->shipment_id)->first()
                : null);

        $safetyTotals = DriverNotification::query()
            ->where('driver_id', $driver->id)
            ->where('assignment_id', $assignment->id)
            ->whereIn('code', [
                DriverNotificationCode::SPEED_EXCESS,
                DriverNotificationCode::HARSH_BRAKING,
                DriverNotificationCode::SUDDEN_STOP,
            ])
            ->groupBy('code')
            ->selectRaw('code, COUNT(*) as total')
            ->pluck('total', 'code');

        $speedAlertCount = (int) ($safetyTotals[DriverNotificationCode::SPEED_EXCESS] ?? 0);
        $harshBrakingCount = (int) ($safetyTotals[DriverNotificationCode::HARSH_BRAKING] ?? 0);
        $suddenStopCount = (int) ($safetyTotals[DriverNotificationCode::SUDDEN_STOP] ?? 0);

        return ApiResult::success([
            'assignment' => $assignment,
            'challenges' => $challenges,
            'issues' => $issues,
            'proof' => $proof,
            'speed_alert_count' => $speedAlertCount,
            'harsh_braking_count' => $harshBrakingCount,
            'sudden_stop_count' => $suddenStopCount,
        ], 'Assignment summary loaded');
    }
}
