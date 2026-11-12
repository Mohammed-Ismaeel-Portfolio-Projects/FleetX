<?php

namespace App\Support;

use App\Actions\Driver\WriteDriverNotificationAction;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\DriverNotification;
use Throwable;

/**
 * Best-effort Driver notification helpers (D8). Never throws to callers.
 */
final class DriverNotificationWriter
{
    public static function notify(
        int $driverId,
        string $code,
        string $title,
        string $body,
        string $severity = 'info',
        ?Assignment $assignment = null,
        ?string $actionKind = null,
        ?string $actionTarget = null,
        ?string $dedupeKey = null
    ): void {
        $row = null;
        try {
            if (! Driver::query()->whereKey($driverId)->exists()) {
                return;
            }

            $result = app(WriteDriverNotificationAction::class)->execute([
                'driver_id' => $driverId,
                'code' => $code,
                'title' => $title,
                'body' => $body,
                'severity' => $severity,
                'assignment_id' => $assignment?->id,
                'shipment_id' => $assignment?->shipment_id,
                'action_kind' => $actionKind,
                'action_target' => $actionTarget,
                'dedupe_key' => $dedupeKey,
            ]);

            if ($result->success && $result->data instanceof DriverNotification && $result->data->wasRecentlyCreated) {
                $row = $result->data;
            }
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if ($row instanceof DriverNotification) {
            app(DriverPushDispatcher::class)->dispatchBestEffort($row);
        }
    }

    public static function forAssignment(
        Assignment $assignment,
        string $code,
        string $title,
        string $body,
        string $severity,
        string $actionKind,
        ?string $dedupeSuffix = null
    ): void {
        if (! $assignment->driver_id) {
            return;
        }

        $target = $actionKind === DriverNotificationAction::KIND_WORK
            ? null
            : (string) $assignment->id;

        self::notify(
            (int) $assignment->driver_id,
            $code,
            $title,
            $body,
            $severity,
            $assignment,
            $actionKind,
            $target,
            $dedupeSuffix !== null
                ? $code.':'.$assignment->id.':'.$dedupeSuffix
                : $code.':'.$assignment->id
        );
    }
}
