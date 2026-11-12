<?php

namespace App\Actions\Shipment;

use App\Actions\Driver\IssueDriverVerificationChallengeAction;
use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\User;
use App\Support\DriverExecutionPhase;
use App\Support\DriverVerificationEvent;
use Illuminate\Support\Str;

/**
 * Company-scoped client verification QR retrieval (CLIENT-QR-02).
 * Reuses IssueDriverVerificationChallengeAction to resolve or issue open challenge.
 */
class GetClientShipmentVerificationQrAction
{
    public function __construct(
        private readonly IssueDriverVerificationChallengeAction $issueChallengeAction
    ) {}

    public function execute(User $user, string $shipmentId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        if (! Str::isUuid($shipmentId)) {
            return ApiResult::notFound('Shipment not found');
        }

        /** @var Shipment|null $shipment */
        $shipment = Shipment::query()
            ->forCustomer($companyId)
            ->with(['activeAssignment'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        $assignment = $shipment->activeAssignment;
        if ($assignment === null || ! $assignment->is_active) {
            return ApiResult::success(null, 'No active verification QR');
        }

        $phase = $assignment->execution_phase;

        $event = match ($phase) {
            DriverExecutionPhase::PICKUP_VERIFYING => DriverVerificationEvent::PICKUP_VERIFY,
            DriverExecutionPhase::DELIVERY_VERIFYING => DriverVerificationEvent::DELIVERY_VERIFY,
            default => null,
        };

        if ($event === null) {
            return ApiResult::success(null, 'No active verification QR');
        }

        $challenge = $this->issueChallengeAction->ensureForAssignment($assignment, $event);
        if ($challenge === null) {
            return ApiResult::success(null, 'No active verification QR');
        }

        return ApiResult::success([
            'event' => $event,
            'qr_payload' => $challenge->qrPayload(),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'status' => 'active',
        ], 'Active verification QR retrieved successfully');
    }
}
