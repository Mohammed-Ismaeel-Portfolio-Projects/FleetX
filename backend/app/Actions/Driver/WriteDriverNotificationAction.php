<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Driver;
use App\Models\DriverNotification;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Persist one Driver operational notification (D8).
 * Best-effort: callers must not fail primary domain actions on write failure.
 */
class WriteDriverNotificationAction
{
    /**
     * @param  array{
     *     driver_id: int,
     *     code: string,
     *     title: string,
     *     body: string,
     *     severity?: string,
     *     assignment_id?: int|null,
     *     shipment_id?: string|null,
     *     action_kind?: string|null,
     *     action_target?: string|null,
     *     dedupe_key?: string|null
     * }  $data
     */
    public function execute(array $data): ApiResult
    {
        try {
            $driverId = (int) ($data['driver_id'] ?? 0);
            $code = is_string($data['code'] ?? null) ? trim($data['code']) : '';
            $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';
            $body = is_string($data['body'] ?? null) ? trim($data['body']) : '';
            $severity = is_string($data['severity'] ?? null) ? trim($data['severity']) : 'info';
            $dedupeKey = isset($data['dedupe_key']) && is_string($data['dedupe_key'])
                ? trim($data['dedupe_key'])
                : null;
            if ($dedupeKey === '') {
                $dedupeKey = null;
            }

            if ($driverId <= 0 || ! Driver::query()->whereKey($driverId)->exists()) {
                return ApiResult::validation(['driver_id' => ['Valid driver is required.']]);
            }

            if (! in_array($code, DriverNotificationCode::all(), true)) {
                return ApiResult::validation(['code' => ['Unknown notification code.']]);
            }

            if ($title === '' || $body === '') {
                return ApiResult::validation([
                    'title' => ['Title is required.'],
                    'body' => ['Body is required.'],
                ]);
            }

            if (! in_array($severity, ['info', 'warning', 'critical'], true)) {
                $severity = 'info';
            }

            $actionKind = $data['action_kind'] ?? null;
            $actionTarget = isset($data['action_target']) ? (string) $data['action_target'] : null;
            if ($actionKind !== null && ! DriverNotificationAction::isValidKind($actionKind)) {
                $actionKind = null;
                $actionTarget = null;
            }
            if ($actionKind === DriverNotificationAction::KIND_WORK) {
                $actionTarget = null;
            }

            return DB::transaction(function () use (
                $driverId,
                $code,
                $title,
                $body,
                $severity,
                $dedupeKey,
                $data,
                $actionKind,
                $actionTarget
            ) {
                if ($dedupeKey !== null) {
                    $existing = DriverNotification::query()
                        ->where('driver_id', $driverId)
                        ->where('dedupe_key', $dedupeKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        return ApiResult::success($existing, 'Notification already exists');
                    }
                }

                $row = DriverNotification::query()->create([
                    'driver_id' => $driverId,
                    'code' => $code,
                    'title' => $title,
                    'body' => $body,
                    'severity' => $severity,
                    'read_at' => null,
                    'assignment_id' => $data['assignment_id'] ?? null,
                    'shipment_id' => $data['shipment_id'] ?? null,
                    'action_kind' => $actionKind,
                    'action_target' => $actionTarget,
                    'dedupe_key' => $dedupeKey,
                ]);

                return ApiResult::success($row, 'Driver notification written');
            });
        } catch (Throwable $e) {
            report($e);

            return ApiResult::error('Failed to persist Driver notification', 500);
        }
    }
}
