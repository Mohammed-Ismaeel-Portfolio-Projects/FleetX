<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authenticated user + minimum identity linkage for Driver/Client apps.
 * No messaging fields.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ]),
            /**
             * Linked Driver employee (when role=driver and user_id is set).
             * Null when unlinked — callers must treat as unavailable, not invent IDs.
             */
            'driver' => $this->when(
                $this->relationLoaded('driver'),
                function () {
                    if (! $this->driver) {
                        return null;
                    }

                    return [
                        'id' => $this->driver->id,
                        'employee_number' => $this->driver->employee_number,
                    ];
                }
            ),
            /**
             * Linked customer Company (when role=client and company_id is set).
             * Null when unlinked.
             */
            'company' => $this->when(
                $this->relationLoaded('company'),
                function () {
                    if (! $this->company) {
                        return null;
                    }

                    return [
                        'id' => $this->company->id,
                        'company_code' => $this->company->company_code,
                        'company_name' => $this->company->company_name,
                    ];
                }
            ),
            'created_at' => $this->created_at,
        ];
    }
}
