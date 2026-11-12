<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $participant = null;
        if ($user && $this->relationLoaded('participants')) {
            $participant = $this->participants->firstWhere('user_id', $user->id);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'shipment_id' => $this->shipment_id,
            'assignment_id' => $this->assignment_id,
            'shipment' => $this->whenLoaded('shipment', fn () => $this->shipment ? [
                'id' => $this->shipment->id,
                'shipment_number' => $this->shipment->shipment_number,
            ] : null),
            'participants' => $this->whenLoaded('participants', function () {
                return $this->participants->map(fn ($p) => [
                    'user_id' => $p->user_id,
                    'participant_role' => $p->participant_role,
                    'last_read_at' => $p->last_read_at?->toISOString(),
                    'user' => $p->relationLoaded('user') && $p->user ? [
                        'id' => $p->user->id,
                        'name' => $p->user->name,
                        'email' => $p->user->email,
                    ] : null,
                ])->values();
            }),
            'last_read_at' => $participant?->last_read_at?->toISOString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
