<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\DriverVerificationEvent;
use Illuminate\Support\Facades\DB;

/**
 * Driver execution phase transition (D4).
 * Does not advance shipment status (D5 owns verification → In Transit / Delivered).
 * Auto-issues verification challenges when entering *_verifying (D5).
 */
class TransitionDriverExecutionPhaseAction
{
    public function __construct(
        private readonly IssueDriverVerificationChallengeAction $issueChallenge
    ) {}

    public function execute(User $user, int|string $assignmentId, string $command): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $commands = DriverExecutionPhase::d4Commands();
        if (! isset($commands[$command])) {
            return ApiResult::error('Unknown execution command.', 422);
        }

        /** @var array<string, string> $fromTo */
        $fromTo = $commands[$command];

        $result = DB::transaction(function () use ($user, $driver, $assignmentId, $fromTo, $command) {
            /** @var Assignment|null $assignment */
            $assignment = Assignment::query()
                ->forDriver($driver->id)
                ->lockForUpdate()
                ->find($assignmentId);

            if ($assignment === null) {
                return ApiResult::notFound('Assignment not found');
            }

            $assignment->loadMissing(['status', 'shipment']);
            $statusName = $assignment->status?->name;

            if (! $assignment->is_active || DriverAssignmentTransitionMap::isTerminal($statusName)) {
                return ApiResult::error('Assignment is not active or has ended.', 409);
            }

            if ($statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                return ApiResult::error('Assignment must be In Operation to change execution phase.', 409);
            }

            if ($assignment->shipment_id === null || $assignment->shipment === null) {
                return ApiResult::error('Assignment has no shipment.', 409);
            }

            $current = $assignment->execution_phase;
            $target = null;
            foreach ($fromTo as $from => $to) {
                if ($current === $from) {
                    $target = $to;
                    break;
                }
            }

            // Idempotent: already at unique target of this command
            $targets = array_values($fromTo);
            $uniqueTarget = $targets[0] ?? null;
            if ($uniqueTarget !== null && $current === $uniqueTarget) {
                $this->maybeIssueChallenge($assignment, $current);
                $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

                return ApiResult::success($assignment, 'Execution phase already applied');
            }

            if ($target === null) {
                return ApiResult::error(
                    'Invalid execution phase transition for command '.$command.'.',
                    409
                );
            }

            $assignment->update([
                'execution_phase' => $target,
                'updated_by' => $user->id,
            ]);

            $this->maybeIssueChallenge($assignment, $target);

            $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

            return ApiResult::success($assignment, 'Execution phase updated successfully');
        });

        if ($result->success && $result->data instanceof Assignment) {
            /** @var Assignment $assignment */
            $assignment = $result->data;
            if ($command === 'pickup_arrive') {
                DriverNotificationWriter::forAssignment(
                    $assignment,
                    DriverNotificationCode::PICKUP_ARRIVAL,
                    'Arrived at pickup',
                    'Pickup arrival recorded. Continue loading when ready.',
                    'info',
                    DriverNotificationAction::KIND_ACTIVE_TRIP
                );
            }
            if ($command === 'delivery_arrive') {
                DriverNotificationWriter::forAssignment(
                    $assignment,
                    DriverNotificationCode::DELIVERY_ARRIVAL,
                    'Arrived at delivery',
                    'Delivery arrival recorded. Continue unloading when ready.',
                    'info',
                    DriverNotificationAction::KIND_ACTIVE_TRIP
                );
            }
        }

        return $result;
    }

    private function maybeIssueChallenge(Assignment $assignment, ?string $phase): void
    {
        $event = DriverVerificationEvent::fromPhase($phase);
        if ($event === null) {
            return;
        }

        $this->issueChallenge->ensureForAssignment($assignment, $event);
    }
}
