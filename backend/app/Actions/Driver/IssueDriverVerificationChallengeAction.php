<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\DriverVerificationChallenge;
use App\Models\Shipment;
use App\Models\User;
use App\Support\DriverVerificationEvent;
use Illuminate\Support\Facades\DB;

/**
 * Issue (or re-issue) an opaque Driver verification challenge (D5).
 * Not callable as a trusted token mint from the Driver App client for self-service bypass.
 */
class IssueDriverVerificationChallengeAction
{
    /**
     * @param  array{created_by?: int|null}  $options
     */
    public function execute(
        Shipment $shipment,
        Assignment $assignment,
        string $event,
        array $options = []
    ): ApiResult {
        if (! in_array($event, DriverVerificationEvent::all(), true)) {
            return ApiResult::error('Invalid verification event.', 422, [
                'code' => 'invalid_challenge',
            ]);
        }

        if ((string) $assignment->shipment_id !== (string) $shipment->id) {
            return ApiResult::error('Assignment does not belong to shipment.', 422, [
                'code' => 'wrong_shipment',
            ]);
        }

        $ttl = max(1, (int) config('driver_verification.ttl_minutes', 60));

        $challenge = DB::transaction(function () use ($shipment, $assignment, $event, $ttl) {
            DriverVerificationChallenge::query()
                ->where('shipment_id', $shipment->id)
                ->where('event', $event)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(function (DriverVerificationChallenge $prior) {
                    $prior->update(['revoked_at' => now()]);
                });

            return DriverVerificationChallenge::query()->create([
                'shipment_id' => $shipment->id,
                'assignment_id' => $assignment->id,
                'event' => $event,
                'expires_at' => now()->addMinutes($ttl),
            ]);
        });

        return ApiResult::success([
            'challenge' => $challenge,
            'qr_payload' => $challenge->qrPayload(),
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'event' => $challenge->event,
        ], 'Verification challenge issued');
    }

    /**
     * Ensure an open challenge exists for verifying phase (auto-issue).
     */
    public function ensureForAssignment(Assignment $assignment, string $event): ?DriverVerificationChallenge
    {
        $assignment->loadMissing('shipment');
        if ($assignment->shipment === null) {
            return null;
        }

        $open = DriverVerificationChallenge::query()
            ->where('assignment_id', $assignment->id)
            ->where('shipment_id', $assignment->shipment_id)
            ->where('event', $event)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $result = $this->execute($assignment->shipment, $assignment, $event);
        if (! $result->success) {
            return null;
        }

        /** @var array{challenge: DriverVerificationChallenge} $data */
        $data = $result->data;

        return $data['challenge'];
    }
}
