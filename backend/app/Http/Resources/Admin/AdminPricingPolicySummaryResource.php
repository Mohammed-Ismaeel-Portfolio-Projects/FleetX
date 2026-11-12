<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin pricing policy list item (PRICING-02). Policy fields only — no nested rules.
 */
class AdminPricingPolicySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status?->value,
            'priority' => $this->priority,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->format('Y-m-d H:i:s'),
            'effective_until' => $this->effective_until?->format('Y-m-d H:i:s'),
            'tax_rate' => $this->numeric($this->tax_rate),
            'tax_inclusive' => (bool) $this->tax_inclusive,
            'minimum_fare' => $this->numeric($this->minimum_fare),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return (float) $value;
    }
}
