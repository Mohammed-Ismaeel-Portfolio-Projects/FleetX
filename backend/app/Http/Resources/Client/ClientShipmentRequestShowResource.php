<?php

namespace App\Http\Resources\Client;

use App\Models\ShipmentOrder;
use App\Support\Client\ClientShipmentRequestReviewFeedback;
use App\Support\ClientShipmentRequestCoordinateRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe Shipment Request detail payload (C2.9-A3.4.1 / C5.3).
 * Full approved client-safe request fields — no operational or internal data.
 * review_feedback is an allowlisted projection from status-history reason only.
 */
class ClientShipmentRequestShowResource extends JsonResource
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
            'status_id' => $this->status_id,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'type' => $this->status->type,
            ]),
            'submission_revision' => $this->submission_revision,
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
            'review_feedback' => $this->reviewFeedback(),
            'linked_shipment' => ClientLinkedShipmentResource::summary(
                $this->relationLoaded('convertedShipment') ? $this->convertedShipment : null
            ),
            'price_snapshot' => $this->relationLoaded('priceBreakdown') && $this->priceBreakdown !== null
                ? new ClientShipmentOrderPriceSnapshotResource($this->priceBreakdown)
                : null,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array{message: string}|null
     */
    private function reviewFeedback(): ?array
    {
        /** @var ShipmentOrder $order */
        $order = $this->resource;

        return ClientShipmentRequestReviewFeedback::resolve($order);
    }
}
