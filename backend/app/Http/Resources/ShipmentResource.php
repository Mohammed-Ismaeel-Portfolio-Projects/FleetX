<?php

namespace App\Http\Resources;

use App\Http\Resources\Shipment\OpsShipmentFinalPriceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'customer_company_id' => $this->customer_company_id,
            'customer_company' => $this->whenLoaded('customerCompany', fn () => [
                'id' => $this->customerCompany->id,
                'name' => $this->customerCompany->company_name,
                'company_name' => $this->customerCompany->company_name,
                'company_code' => $this->customerCompany->company_code,
            ]),
            'shipment_order_id' => $this->shipment_order_id,
            'shipment_order' => $this->whenLoaded('shipmentOrder', fn () => $this->shipmentOrder
                ? [
                    'id' => $this->shipmentOrder->id,
                    'request_number' => $this->shipmentOrder->request_number,
                    'reference_number' => $this->shipmentOrder->reference_number,
                    'status' => $this->shipmentOrder->relationLoaded('status') && $this->shipmentOrder->status
                        ? [
                            'id' => $this->shipmentOrder->status->id,
                            'name' => $this->shipmentOrder->status->name,
                            'type' => $this->shipmentOrder->status->type,
                        ]
                        : null,
                ]
                : null),
            'reference_number' => $this->reference_number,
            'priority' => $this->priority,
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'cargo_weight' => $this->cargo_weight,
            'cargo_volume' => $this->cargo_volume,
            'package_count' => $this->package_count,
            'hazardous_cargo' => $this->hazardous_cargo,
            'temperature_controlled' => $this->temperature_controlled,
            'pickup_company' => $this->pickup_company,
            'pickup_contact' => $this->pickup_contact,
            'pickup_phone' => $this->pickup_phone,
            'pickup_address' => $this->pickup_address,
            'pickup_city' => $this->pickup_city,
            'pickup_country' => $this->pickup_country,
            'delivery_company' => $this->delivery_company,
            'delivery_contact' => $this->delivery_contact,
            'delivery_phone' => $this->delivery_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_city' => $this->delivery_city,
            'delivery_country' => $this->delivery_country,
            'planned_pickup_date' => $this->planned_pickup_date?->format('Y-m-d'),
            'planned_delivery_date' => $this->planned_delivery_date?->format('Y-m-d'),
            'actual_pickup_date' => $this->actual_pickup_date?->format('Y-m-d'),
            'actual_delivery_date' => $this->actual_delivery_date?->format('Y-m-d'),
            'special_instructions' => $this->special_instructions,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'updated_by' => $this->updated_by,
            'updater' => $this->whenLoaded('updater', fn () => [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
                'email' => $this->updater->email,
            ]),
            'active_assignment' => $this->whenLoaded('activeAssignment', fn () => $this->serializeAssignment($this->activeAssignment)),
            'latest_assignment' => $this->whenLoaded('latestAssignment', fn () => $this->serializeAssignment($this->latestAssignment)),
            // C2.9-C2.8 Step 6 — read-only Engine ops (cache + enrollment). Never mutates Engine.
            'engine_ops' => app(\App\Actions\Shipment\BuildShipmentEngineOpsReadModelAction::class)
                ->execute($this->resource),
            'status_history' => $this->whenLoaded(
                'statusHistory',
                fn () => ShipmentStatusHistoryResource::collection($this->statusHistory)
            ),
            'tracking_logs' => $this->whenLoaded('trackingLogs', fn () => $this->trackingLogs->map(fn ($log) => [
                'id' => $log->id,
                'latitude' => $log->latitude !== null ? (float) $log->latitude : null,
                'longitude' => $log->longitude !== null ? (float) $log->longitude : null,
                'speed' => $log->speed !== null ? (float) $log->speed : null,
                'recorded_at' => $log->recorded_at?->format('Y-m-d H:i:s'),
            ])),
            'final_price' => $this->relationLoaded('finalPriceBreakdown')
                && $this->finalPriceBreakdown !== null
                && $this->finalPriceBreakdown->isFinalized()
                ? new OpsShipmentFinalPriceResource($this->finalPriceBreakdown)
                : null,
            'final_price_summary' => $this->finalPriceSummary(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     total: float,
     *     currency: string,
     *     price_delta: float,
     *     change_direction: string,
     *     material_change: bool,
     *     calculated_at: string|null
     * }|null
     */
    private function finalPriceSummary(): ?array
    {
        if (! $this->relationLoaded('finalPriceBreakdown')
            || $this->finalPriceBreakdown === null
            || ! $this->finalPriceBreakdown->isFinalized()) {
            return null;
        }

        $final = $this->finalPriceBreakdown;

        return [
            'status' => $final->status->value,
            'total' => (float) $final->final_total,
            'currency' => $final->currency,
            'price_delta' => (float) $final->price_delta,
            'change_direction' => $final->change_direction->value,
            'material_change' => $final->material_change,
            'calculated_at' => $final->calculated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeAssignment(?\App\Models\Assignment $assignment): ?array
    {
        if (! $assignment) {
            return null;
        }

        return [
            'id' => $assignment->id,
            'assignment_code' => $assignment->assignment_code,
            'is_active' => (bool) $assignment->is_active,
            'status_id' => $assignment->status_id,
            'engine_enrollment_status' => $assignment->engine_enrollment_status,
            'engine_enrolled_at' => $assignment->engine_enrolled_at?->format('Y-m-d H:i:s'),
            'status' => $assignment->relationLoaded('status') && $assignment->status
                ? [
                    'id' => $assignment->status->id,
                    'name' => $assignment->status->name,
                    'type' => $assignment->status->type,
                ]
                : null,
            'driver' => $assignment->relationLoaded('driver') && $assignment->driver
                ? [
                    'id' => $assignment->driver->id,
                    'first_name' => $assignment->driver->first_name,
                    'last_name' => $assignment->driver->last_name,
                    'employee_number' => $assignment->driver->employee_number,
                    'phone' => $assignment->driver->phone,
                ]
                : null,
            'vehicle' => $assignment->relationLoaded('vehicle') && $assignment->vehicle
                ? [
                    'id' => $assignment->vehicle->id,
                    'operational_code' => $assignment->vehicle->operational_code,
                    'plate_number' => $assignment->vehicle->plate_number,
                ]
                : null,
            'trailer' => $assignment->relationLoaded('trailer') && $assignment->trailer
                ? [
                    'id' => $assignment->trailer->id,
                    'plate_number' => $assignment->trailer->plate_number,
                    'trailer_type' => $assignment->trailer->trailer_type,
                ]
                : null,
        ];
    }
}
