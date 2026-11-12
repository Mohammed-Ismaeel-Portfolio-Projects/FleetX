<?php

namespace App\Actions\Driver;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Assignment;
use App\Models\DriverVerificationChallenge;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\DriverVerificationError;
use App\Support\DriverVerificationEvent;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomic Driver QR verification (D5).
 * Consumes challenge + advances execution phase + shipment via TransitionShipmentStatusAction.
 */
class VerifyDriverAssignmentAction
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionShipment
    ) {}

    public function execute(
        User $user,
        int|string $assignmentId,
        string $event,
        ?string $challengeInput
    ): ApiResult {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403, [
                'code' => DriverVerificationError::UNAUTHORIZED_DRIVER,
            ]);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403, [
                'code' => DriverVerificationError::UNAUTHORIZED_DRIVER,
            ]);
        }

        if (! in_array($event, DriverVerificationEvent::all(), true)) {
            return ApiResult::error('Invalid verification event.', 422, [
                'code' => DriverVerificationError::WRONG_EVENT,
            ]);
        }

        $challengeId = $this->parseChallengeId($challengeInput);
        if ($challengeId === null) {
            return ApiResult::error('Verification challenge is required.', 422, [
                'code' => DriverVerificationError::INVALID_CHALLENGE,
            ]);
        }

        $requiredPhase = DriverVerificationEvent::requiredPhase($event);
        $notifyShipment = null;
        $notifyTo = null;

        try {
            $result = DB::transaction(function () use (
                $user,
                $driver,
                $assignmentId,
                $event,
                $challengeId,
                $requiredPhase,
                &$notifyShipment,
                &$notifyTo
            ) {
                /** @var Assignment|null $assignment */
                $assignment = Assignment::query()
                    ->forDriver($driver->id)
                    ->lockForUpdate()
                    ->find($assignmentId);

                if ($assignment === null) {
                    return ApiResult::notFound('Assignment not found');
                }

                $assignment->loadMissing(['status', 'shipment.status']);
                $statusName = $assignment->status?->name;

                if (! $assignment->is_active || DriverAssignmentTransitionMap::isTerminal($statusName)) {
                    return ApiResult::error('Assignment is not active or has ended.', 409, [
                        'code' => DriverVerificationError::ASSIGNMENT_INACTIVE,
                    ]);
                }

                if ($statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                    return ApiResult::error('Assignment must be In Operation to verify.', 409, [
                        'code' => DriverVerificationError::ASSIGNMENT_INACTIVE,
                    ]);
                }

                if ($assignment->shipment === null) {
                    return ApiResult::error('Assignment has no shipment.', 409, [
                        'code' => DriverVerificationError::WRONG_SHIPMENT,
                    ]);
                }

                $phase = $assignment->execution_phase;

                // Idempotent success: already past this verification for this assignment
                if ($event === DriverVerificationEvent::PICKUP_VERIFY
                    && in_array($phase, [
                        DriverExecutionPhase::PICKUP_VERIFIED,
                        DriverExecutionPhase::DELIVERY_EN_ROUTE,
                        DriverExecutionPhase::DELIVERY_ARRIVED,
                        DriverExecutionPhase::DELIVERY_UNLOADING,
                        DriverExecutionPhase::DELIVERY_VERIFYING,
                        DriverExecutionPhase::DELIVERY_VERIFIED,
                    ], true)
                ) {
                    $prior = DriverVerificationChallenge::query()
                        ->whereKey($challengeId)
                        ->first();
                    if ($prior !== null
                        && $prior->isConsumed()
                        && (int) $prior->assignment_id === (int) $assignment->id
                        && $prior->event === $event
                    ) {
                        return $this->successPayload($assignment, $event, $prior);
                    }
                }

                if ($event === DriverVerificationEvent::DELIVERY_VERIFY
                    && $phase === DriverExecutionPhase::DELIVERY_VERIFIED
                ) {
                    $prior = DriverVerificationChallenge::query()
                        ->whereKey($challengeId)
                        ->first();
                    if ($prior !== null
                        && $prior->isConsumed()
                        && (int) $prior->assignment_id === (int) $assignment->id
                        && $prior->event === $event
                    ) {
                        return $this->successPayload($assignment, $event, $prior);
                    }
                }

                if ($phase !== $requiredPhase) {
                    return ApiResult::error('Verification is not allowed in the current phase.', 409, [
                        'code' => DriverVerificationError::INVALID_PHASE,
                    ]);
                }

                /** @var DriverVerificationChallenge|null $challenge */
                $challenge = DriverVerificationChallenge::query()
                    ->whereKey($challengeId)
                    ->lockForUpdate()
                    ->first();

                if ($challenge === null) {
                    return ApiResult::error('Verification challenge is invalid.', 404, [
                        'code' => DriverVerificationError::INVALID_CHALLENGE,
                    ]);
                }

                if ((int) $challenge->assignment_id !== (int) $assignment->id) {
                    return ApiResult::error('Verification challenge is invalid.', 404, [
                        'code' => DriverVerificationError::WRONG_ASSIGNMENT,
                    ]);
                }

                if ((string) $challenge->shipment_id !== (string) $assignment->shipment_id) {
                    return ApiResult::error('Verification challenge is invalid.', 404, [
                        'code' => DriverVerificationError::WRONG_SHIPMENT,
                    ]);
                }

                if ($challenge->event !== $event) {
                    return ApiResult::error('Verification challenge does not match this event.', 409, [
                        'code' => DriverVerificationError::WRONG_EVENT,
                    ]);
                }

                if ($challenge->isRevoked()) {
                    return ApiResult::error('Verification challenge has been revoked.', 409, [
                        'code' => DriverVerificationError::REVOKED_CHALLENGE,
                    ]);
                }

                if ($challenge->isConsumed()) {
                    return ApiResult::error('Verification challenge has already been used.', 409, [
                        'code' => DriverVerificationError::ALREADY_CONSUMED,
                    ]);
                }

                if ($challenge->isExpired()) {
                    return ApiResult::error('Verification challenge has expired.', 409, [
                        'code' => DriverVerificationError::EXPIRED_CHALLENGE,
                    ]);
                }

                $challenge->update([
                    'consumed_at' => now(),
                    'consumed_by_user_id' => $user->id,
                ]);

                if ($event === DriverVerificationEvent::PICKUP_VERIFY) {
                    $assignment->update([
                        'execution_phase' => DriverExecutionPhase::PICKUP_VERIFIED,
                        'updated_by' => $user->id,
                    ]);

                    $shipment = $this->transitionShipment->execute(
                        $assignment->shipment,
                        ShipmentStatus::IN_TRANSIT,
                        $user->id,
                        now()->toDateString(),
                        null,
                        false
                    );
                    $notifyShipment = $shipment;
                    $notifyTo = ShipmentStatus::IN_TRANSIT;

                    $assignment->update([
                        'execution_phase' => DriverExecutionPhase::DELIVERY_EN_ROUTE,
                        'updated_by' => $user->id,
                    ]);
                } else {
                    $assignment->update([
                        'execution_phase' => DriverExecutionPhase::DELIVERY_VERIFIED,
                        'updated_by' => $user->id,
                    ]);

                    $shipment = $this->transitionShipment->execute(
                        $assignment->shipment,
                        ShipmentStatus::DELIVERED,
                        $user->id,
                        null,
                        now()->toDateString(),
                        false
                    );
                    $notifyShipment = $shipment;
                    $notifyTo = ShipmentStatus::DELIVERED;
                }

                $assignment->refresh();
                $assignment->load(['status', 'shipment.status', 'vehicle', 'trailer']);

                return $this->successPayload($assignment, $event, $challenge->fresh());
            });
        } catch (InvalidShipmentTransitionException $e) {
            return ApiResult::error($e->getMessage(), 409, [
                'code' => DriverVerificationError::SHIPMENT_TRANSITION_INVALID,
            ]);
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() >= 400 ? (int) $e->getCode() : 422, [
                'code' => DriverVerificationError::SHIPMENT_TRANSITION_INVALID,
            ]);
        }

        if ($result->success && $notifyShipment !== null && $notifyTo !== null) {
            $this->transitionShipment->notifyClientOfSuccessfulTransitionPublic($notifyShipment, $notifyTo);
        }

        if ($result->success && is_array($result->data) && isset($result->data['assignment'])) {
            /** @var Assignment $assignment */
            $assignment = $result->data['assignment'];
            $isPickup = $event === DriverVerificationEvent::PICKUP_VERIFY;
            DriverNotificationWriter::forAssignment(
                $assignment,
                $isPickup
                    ? DriverNotificationCode::PICKUP_VERIFICATION_SUCCESS
                    : DriverNotificationCode::DELIVERY_VERIFICATION_SUCCESS,
                $isPickup ? 'Pickup verified' : 'Delivery verified',
                $isPickup
                    ? 'Pickup verification succeeded. Continue to delivery.'
                    : 'Delivery verification succeeded. Complete proof of delivery when ready.',
                'info',
                DriverNotificationAction::KIND_ACTIVE_TRIP
            );
        }

        return $result;
    }

    private function successPayload(
        Assignment $assignment,
        string $event,
        DriverVerificationChallenge $challenge
    ): ApiResult {
        $assignment->loadMissing(['status', 'shipment.status', 'vehicle', 'trailer']);

        return ApiResult::success([
            'assignment' => $assignment,
            'verification' => [
                'event' => $event,
                'verified_at' => $challenge->consumed_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
                'challenge_id' => $challenge->id,
            ],
        ], 'Verification successful');
    }

    private function parseChallengeId(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        if (Str::isUuid($trimmed)) {
            return strtolower($trimmed);
        }

        if (preg_match('/[?&]c=([0-9a-fA-F-]{36})/', $trimmed, $m) === 1) {
            return strtolower($m[1]);
        }

        // Bare path fragment after last /
        if (preg_match('/([0-9a-fA-F-]{36})\s*$/', $trimmed, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }
}
