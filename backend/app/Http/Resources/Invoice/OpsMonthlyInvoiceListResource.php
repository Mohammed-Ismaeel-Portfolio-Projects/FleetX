<?php

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops monthly invoice list item (Phase 6c).
 */
class OpsMonthlyInvoiceListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'company_code' => $this->company->company_code,
                'company_name' => $this->company->company_name,
            ]),
            'billing_period' => [
                'start' => $this->billing_period_start?->format('Y-m-d'),
                'end' => $this->billing_period_end?->format('Y-m-d'),
            ],
            'currency' => $this->currency,
            'status' => $this->status?->value,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'issued_at' => $this->issued_at?->format('Y-m-d H:i:s'),
            'line_item_count' => $this->when(
                isset($this->line_items_count),
                fn () => (int) $this->line_items_count,
            ),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
