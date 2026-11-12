<?php

namespace App\Actions\Driver;

use App\Actions\Tracking\CreateTrackingLogAction;
use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\TrackingLog;
use App\Models\TrackingState;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Driver telemetry ingest (D6). Server binds shipment/driver/assignment ownership.
 */
class SubmitDriverTrackingAction
{
    public function __construct(
        private readonly CreateTrackingLogAction $createTrackingLog
    ) {}

    /**
     * @param  array{
     *   latitude?: mixed,
     *   longitude?: mixed,
     *   accuracy?: mixed,
     *   speed?: mixed,
     *   heading?: mixed,
     *   recorded_at?: mixed
     * }  $payload
     */
    public function execute(User $user, int|string $assignmentId, array $payload): ApiResult
    {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        $validated = $this->validatePayload($payload);
        if ($validated instanceof ApiResult) {
            return $validated;
        }

        $result = DB::transaction(function () use ($user, $driver, $assignmentId, $validated) {
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
                return ApiResult::error('Telemetry is only accepted while the assignment is In Operation.', 409);
            }

            if ($assignment->shipment_id === null || $assignment->shipment === null) {
                return ApiResult::error('Assignment has no shipment.', 409);
            }

            $receivedAt = now();

            // Lightweight dedupe: identical coarse point within short window
            $prior = TrackingLog::query()
                ->where('shipment_id', $assignment->shipment_id)
                ->where('recorded_at', '>=', $receivedAt->copy()->subSeconds(
                    max(1, (int) config('driver_tracking.dedupe_window_seconds', 2))
                ))
                ->orderByDesc('recorded_at')
                ->first();

            if ($prior !== null
                && round((float) $prior->latitude, 5) === round($validated['latitude'], 5)
                && round((float) $prior->longitude, 5) === round($validated['longitude'], 5)
            ) {
                $state = TrackingState::query()
                    ->where('assignment_id', $assignment->id)
                    ->first();

                return ApiResult::success([
                    'tracking_log_id' => $prior->id,
                    'deduped' => true,
                    'position' => $this->positionProjection($prior, $state, $receivedAt),
                ], 'Telemetry already recorded');
            }

            $result = $this->createTrackingLog->execute([
                'shipment_id' => $assignment->shipment_id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'speed' => $validated['speed'],
                'heading' => $validated['heading'],
                'accuracy' => $validated['accuracy'],
                'recorded_at' => $validated['recorded_at'],
                'received_at' => $receivedAt,
            ]);

            if (! $result->success) {
                return $result;
            }

            /** @var TrackingLog $log */
            $log = $result->data;
            $state = TrackingState::query()
                ->where('assignment_id', $assignment->id)
                ->first();

            return ApiResult::success([
                'tracking_log_id' => $log->id,
                'deduped' => false,
                'position' => $this->positionProjection($log, $state, $receivedAt),
            ], 'Telemetry accepted');
        });

        if ($result->success
            && is_array($result->data)
            && ($result->data['deduped'] ?? false) === false
            && isset($result->data['tracking_log_id'])
        ) {
            try {
                app(EvaluateDriverSpeedSafetyAction::class)->execute(
                    (int) $assignmentId,
                    (int) $result->data['tracking_log_id']
                );
            } catch (Throwable $e) {
                report($e);
            }

            try {
                app(EvaluateDriverMotionSafetyAction::class)->execute(
                    (int) $assignmentId,
                    (int) $result->data['tracking_log_id']
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{latitude: float, longitude: float, accuracy: ?float, speed: ?float, heading: ?float, recorded_at: Carbon}|ApiResult
     */
    private function validatePayload(array $payload): array|ApiResult
    {
        $lat = $this->finiteFloat($payload['latitude'] ?? null);
        $lng = $this->finiteFloat($payload['longitude'] ?? null);

        if ($lat === null || $lng === null) {
            return ApiResult::validation(['coordinates' => ['Valid latitude and longitude are required.']], 'Invalid telemetry');
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return ApiResult::validation(['coordinates' => ['Coordinates are out of range.']], 'Invalid telemetry');
        }

        // Reject null-island unless domain explicitly allows (it does not)
        if (abs($lat) < 0.00001 && abs($lng) < 0.00001) {
            return ApiResult::validation(['coordinates' => ['Invalid coordinates.']], 'Invalid telemetry');
        }

        $accuracy = $this->optionalFloat($payload['accuracy'] ?? null);
        if ($accuracy !== null && ($accuracy < 0 || $accuracy > 5000)) {
            return ApiResult::validation(['accuracy' => ['Accuracy is invalid.']], 'Invalid telemetry');
        }

        $maxAccuracy = (float) config('driver_tracking.max_accuracy_meters', 200);
        if ($accuracy !== null && $accuracy > $maxAccuracy) {
            // Accept but do not treat as high confidence — still store
        }

        $speed = $this->optionalFloat($payload['speed'] ?? null);
        if ($speed !== null && ($speed < 0 || $speed > 300)) {
            return ApiResult::validation(['speed' => ['Speed is invalid.']], 'Invalid telemetry');
        }

        $heading = $this->optionalFloat($payload['heading'] ?? null);
        if ($heading !== null) {
            if ($heading < 0 || $heading > 360) {
                return ApiResult::validation(['heading' => ['Heading must be between 0 and 360.']], 'Invalid telemetry');
            }
        }

        $recordedAt = $this->parseRecordedAt($payload['recorded_at'] ?? null);
        if ($recordedAt instanceof ApiResult) {
            return $recordedAt;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'speed' => $speed,
            'heading' => $heading,
            'recorded_at' => $recordedAt,
        ];
    }

    private function parseRecordedAt(mixed $raw): Carbon|ApiResult
    {
        if ($raw === null || $raw === '') {
            return now();
        }

        try {
            $at = Carbon::parse($raw);
        } catch (\Throwable) {
            return ApiResult::validation(['recorded_at' => ['recorded_at must be a valid timestamp.']], 'Invalid telemetry');
        }

        $now = now();
        $maxPast = (int) config('driver_tracking.recorded_at_max_past_seconds', 3600);
        $maxFuture = (int) config('driver_tracking.recorded_at_max_future_seconds', 120);

        if ($at->lt($now->copy()->subSeconds($maxPast))) {
            return ApiResult::validation(['recorded_at' => ['recorded_at is too old.']], 'Invalid telemetry');
        }

        if ($at->gt($now->copy()->addSeconds($maxFuture))) {
            return ApiResult::validation(['recorded_at' => ['recorded_at is in the future.']], 'Invalid telemetry');
        }

        return $at;
    }

    private function finiteFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $f = (float) $value;

        return is_finite($f) ? $f : null;
    }

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->finiteFloat($value);
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: ?float, speed: ?float, heading: ?float, recorded_at: ?string, received_at: ?string, source: string}
     */
    private function positionProjection(TrackingLog $log, ?TrackingState $state, Carbon $fallbackReceived): array
    {
        return [
            'latitude' => (float) $log->latitude,
            'longitude' => (float) $log->longitude,
            'accuracy' => $log->accuracy !== null ? (float) $log->accuracy : null,
            'speed' => $log->speed !== null ? (float) $log->speed : null,
            'heading' => $log->heading !== null ? (float) $log->heading : null,
            'recorded_at' => $log->recorded_at?->format('Y-m-d H:i:s'),
            'received_at' => ($log->received_at ?? $state?->last_update_at ?? $fallbackReceived)->format('Y-m-d H:i:s'),
            'source' => 'server_last_known',
        ];
    }
}
