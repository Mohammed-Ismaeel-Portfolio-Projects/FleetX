<?php

namespace App\Http\Resources\ShipmentOrder;

use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ops Shipment Request detail payload (C2.9-B2.1).
 */
class ShipmentRequestShowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'reference_number' => $this->reference_number,
            'customer_company' => $this->whenLoaded('customerCompany', fn () => [
                'id' => $this->customerCompany->id,
                'company_name' => $this->customerCompany->company_name,
                'company_code' => $this->customerCompany->company_code,
            ]),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
                'email' => $this->submittedBy->email,
            ]),
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'submission_revision' => $this->submission_revision,
            'submission_snapshot' => $this->payload_json,
            'cargo_type' => $this->cargo_type,
            'cargo_description' => $this->cargo_description,
            'cargo_weight' => $this->cargo_weight,
            'cargo_volume' => $this->cargo_volume,
            'package_count' => $this->package_count,
            'trailer_type' => $this->trailer_type,
            'hazardous_cargo' => $this->hazardous_cargo,
            'temperature_controlled' => $this->temperature_controlled,
            'pickup_company' => $this->pickup_company,
            'pickup_contact' => $this->pickup_contact,
            'pickup_phone' => $this->pickup_phone,
            'pickup_address' => $this->pickup_address,
            'pickup_city' => $this->pickup_city,
            'pickup_country' => $this->pickup_country,
            'pickup_latitude' => ClientShipmentRequestCoordinateRules::toFloat($this->pickup_latitude),
            'pickup_longitude' => ClientShipmentRequestCoordinateRules::toFloat($this->pickup_longitude),
            'delivery_company' => $this->delivery_company,
            'delivery_contact' => $this->delivery_contact,
            'delivery_phone' => $this->delivery_phone,
            'delivery_address' => $this->delivery_address,
            'delivery_city' => $this->delivery_city,
            'delivery_country' => $this->delivery_country,
            'delivery_latitude' => ClientShipmentRequestCoordinateRules::toFloat($this->delivery_latitude),
            'delivery_longitude' => ClientShipmentRequestCoordinateRules::toFloat($this->delivery_longitude),
            'requested_pickup_date' => $this->requested_pickup_date?->format('Y-m-d'),
            'requested_delivery_date' => $this->requested_delivery_date?->format('Y-m-d'),
            'special_instructions' => $this->special_instructions,
            'client_notes' => $this->client_notes,
            'dispatcher_notes' => $this->dispatcher_notes,
            'priority' => $this->priority,
            'converted_shipment_id' => $this->converted_shipment_id,
            'converted_shipment' => $this->whenLoaded('convertedShipment', fn () => $this->convertedShipment === null ? null : [
                'id' => $this->convertedShipment->id,
                'shipment_number' => $this->convertedShipment->shipment_number,
                'shipment_order_id' => $this->convertedShipment->shipment_order_id,
                'status' => $this->convertedShipment->relationLoaded('status') && $this->convertedShipment->status !== null ? [
                    'id' => $this->convertedShipment->status->id,
                    'name' => $this->convertedShipment->status->name,
                    'type' => $this->convertedShipment->status->type,
                ] : null,
            ]),
            'status_history' => ShipmentOrderStatusHistoryResource::collection(
                $this->whenLoaded('statusHistory')
            ),
            'price_snapshot' => $this->relationLoaded('priceBreakdown') && $this->priceBreakdown !== null
                ? new OpsShipmentOrderPriceSnapshotResource($this->priceBreakdown)
                : null,
            'planned_route' => $this->planned_route_status === null ? null : [
                'status' => $this->planned_route_status,
                'distance_meters' => $this->planned_distance_meters,
                'distance_km' => $this->planned_distance_meters !== null ? round($this->planned_distance_meters / 1000, 1) : null,
                'duration_seconds' => $this->planned_duration_seconds,
                'duration_formatted' => $this->planned_duration_seconds !== null
                    ? sprintf('%dh %02dm', floor($this->planned_duration_seconds / 3600), floor(($this->planned_duration_seconds % 3600) / 60))
                    : null,
                'geometry' => $this->planned_route_geometry,
                'calculated_at' => $this->planned_route_calculated_at?->format('Y-m-d H:i:s'),
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
