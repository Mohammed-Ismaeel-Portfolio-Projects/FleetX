<?php

namespace App\Http\Controllers;

use App\Actions\Driver\IssueDriverVerificationChallengeAction;
use App\Core\ApiResponse;
use App\Http\Requests\Shipment\IssueDriverVerificationChallengeRequest;
use App\Models\Assignment;
use App\Models\DriverVerificationChallenge;
use App\Models\Shipment;
use App\Support\DriverVerificationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ops-facing Driver verification challenge presentation (D5).
 * Does not change Ops dashboard UI — API contract only.
 */
class DriverVerificationChallengeController extends Controller
{
    public function issue(
        IssueDriverVerificationChallengeRequest $request,
        Shipment $shipment,
        IssueDriverVerificationChallengeAction $action
    ): JsonResponse {
        $this->authorize('view', $shipment);

        $event = (string) $request->validated('event');
        $assignmentId = $request->validated('assignment_id');

        $assignmentQuery = Assignment::query()
            ->where('shipment_id', $shipment->id)
            ->where('is_active', true)
            ->orderByDesc('id');

        if ($assignmentId !== null) {
            $assignmentQuery->whereKey($assignmentId);
        }

        $assignment = $assignmentQuery->first();
        if ($assignment === null) {
            return ApiResponse::error('No active assignment found for this shipment.', 409);
        }

        $result = $action->execute($shipment, $assignment, $event);
        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{challenge: DriverVerificationChallenge, qr_payload: string, expires_at: ?string, event: string} $data */
        $data = $result->data;
        $challenge = $data['challenge'];

        return ApiResponse::success([
            'id' => $challenge->id,
            'event' => $challenge->event,
            'shipment_id' => $challenge->shipment_id,
            'assignment_id' => $challenge->assignment_id,
            'qr_payload' => $data['qr_payload'],
            'expires_at' => $data['expires_at'],
            'consumed_at' => null,
            'revoked_at' => null,
        ], $result->message);
    }

    public function active(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $event = (string) $request->query('event', '');
        if (! in_array($event, DriverVerificationEvent::all(), true)) {
            return ApiResponse::error('A valid event query parameter is required.', 422);
        }

        $challenge = DriverVerificationChallenge::query()
            ->where('shipment_id', $shipment->id)
            ->where('event', $event)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if ($challenge === null) {
            return ApiResponse::success(null, 'No active verification challenge');
        }

        return ApiResponse::success([
            'id' => $challenge->id,
            'event' => $challenge->event,
            'shipment_id' => $challenge->shipment_id,
            'assignment_id' => $challenge->assignment_id,
            'qr_payload' => $challenge->qrPayload(),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'consumed_at' => $challenge->consumed_at?->toIso8601String(),
            'revoked_at' => $challenge->revoked_at?->toIso8601String(),
        ], 'Active verification challenge');
    }
}
