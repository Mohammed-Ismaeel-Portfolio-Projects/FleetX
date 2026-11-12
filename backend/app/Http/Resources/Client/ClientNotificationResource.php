<?php

namespace App\Http\Resources\Client;

use App\Models\Notification;
use App\Support\ClientNotificationAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe notification inbox item (C2.8-B / C2.8-C / C2.9-C3.8).
 * Allowlist only — no user_id, company, driver, GPS, or updated_at.
 *
 * `action` is null, or { kind, id } from ClientNotificationAction.
 * Title/message are never used to invent destinations.
 *
 * @mixin Notification
 */
class ClientNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Notification $notification */
        $notification = $this->resource;

        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at?->format('Y-m-d H:i:s'),
            'action' => ClientNotificationAction::toClientPayload(
                $notification->action_kind,
                $notification->action_id
            ),
        ];
    }
}
