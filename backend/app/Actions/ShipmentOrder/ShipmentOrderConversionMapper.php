<?php

namespace App\Actions\ShipmentOrder;

use App\Models\ShipmentOrder;
use App\Support\ShipmentStatus;

/**
 * Maps a persisted ShipmentOrder to CreateShipmentAction input (C2.9-B3.3).
 *
 * Derives payload exclusively from the order row — no caller-supplied Shipment fields.
 */
final class ShipmentOrderConversionMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toShipmentAttributes(ShipmentOrder $order, int $actorUserId): array
    {
        return [
            'customer_company_id' => $order->customer_company_id,
            'reference_number' => $order->reference_number,
            'priority' => $order->priority ?? 'normal',
            'status_id' => ShipmentStatus::conversionInitialStatusId(),
            'baseline_eta_seconds' => $order->planned_duration_seconds,
            'baseline_distance_meters' => $order->planned_distance_meters,
            'baseline_routing_provider' => app(\App\Simulation\Routing\Application\Services\PlannedRouteCache::class)->get((string) $order->id)['provider'] ?? config('routing.default_provider', 'graphhopper'),
            'baseline_calculated_at' => $order->planned_route_calculated_at ?? now(),
            'cargo_type' => $order->cargo_type,
            'cargo_description' => $order->cargo_description,
            'cargo_weight' => $order->cargo_weight,
            'cargo_volume' => $order->cargo_volume,
            'package_count' => $order->package_count,
            'hazardous_cargo' => $order->hazardous_cargo,
            'temperature_controlled' => $order->temperature_controlled,
            'pickup_company' => $order->pickup_company,
            'pickup_contact' => $order->pickup_contact,
            'pickup_phone' => $order->pickup_phone,
            'pickup_address' => $order->pickup_address,
            'pickup_city' => $order->pickup_city,
            'pickup_country' => $order->pickup_country,
            'delivery_company' => $order->delivery_company,
            'delivery_contact' => $order->delivery_contact,
            'delivery_phone' => $order->delivery_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_city' => $order->delivery_city,
            'delivery_country' => $order->delivery_country,
            'planned_pickup_date' => $order->requested_pickup_date,
            'planned_delivery_date' => $order->requested_delivery_date,
            'actual_pickup_date' => null,
            'actual_delivery_date' => null,
            'special_instructions' => $order->special_instructions,
            'notes' => self::mapNotes($order),
            'created_by' => $actorUserId,
            'updated_by' => $actorUserId,
        ];
    }

    private static function mapNotes(ShipmentOrder $order): ?string
    {
        $parts = [];

        if ($order->client_notes !== null && trim($order->client_notes) !== '') {
            $parts[] = trim($order->client_notes);
        }

        if ($order->dispatcher_notes !== null && trim($order->dispatcher_notes) !== '') {
            $parts[] = 'Dispatcher: '.trim($order->dispatcher_notes);
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }
}
