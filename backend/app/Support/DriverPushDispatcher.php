<?php

namespace App\Support;

use App\Models\DriverDevice;
use App\Models\DriverNotification;
use App\Services\Driver\PushNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Best-effort push after inbox persist (D12). Never throws.
 */
final class DriverPushDispatcher
{
    public function dispatchBestEffort(DriverNotification $notification): void
    {
        try {
            if (! filter_var(config('driver_push.enabled'), FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            if (! DriverPushPolicy::shouldPush($notification->code)) {
                return;
            }

            $tokens = DriverDevice::query()
                ->forDriver((int) $notification->driver_id)
                ->active()
                ->pluck('push_token')
                ->filter(fn ($t) => is_string($t) && $t !== '')
                ->values()
                ->all();

            if ($tokens === []) {
                return;
            }

            $priority = $notification->severity === 'critical' ? 'high' : 'default';

            $results = app(PushNotificationService::class)->send($tokens, [
                'title' => (string) $notification->title,
                'body' => (string) $notification->body,
                'priority' => $priority,
                'data' => [
                    'type' => 'driver_notification',
                    'notification_id' => (int) $notification->id,
                    'code' => (string) $notification->code,
                    'action_kind' => $notification->action_kind,
                    'action_target' => $notification->action_target,
                ],
            ]);

            $invalid = [];
            foreach ($results as $row) {
                if (! empty($row['invalid']) && is_string($row['token'])) {
                    $invalid[] = $row['token'];
                }
            }

            if ($invalid !== []) {
                DriverDevice::query()
                    ->whereIn('push_token', $invalid)
                    ->update([
                        'is_active' => false,
                        'invalidated_at' => now(),
                    ]);
            }
        } catch (Throwable $e) {
            report($e);
            Log::warning('driver_push.dispatch_failed', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
