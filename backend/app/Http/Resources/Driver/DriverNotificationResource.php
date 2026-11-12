<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-safe notification projection (D8).
 */
class DriverNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $action = null;
        if ($this->action_kind) {
            $action = [
                'kind' => $this->action_kind,
                'target' => $this->action_target,
            ];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'body' => $this->body,
            'severity' => $this->severity,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'assignment_id' => $this->assignment_id,
            'shipment_id' => $this->shipment_id,
            'action' => $action,
        ];
    }
}
