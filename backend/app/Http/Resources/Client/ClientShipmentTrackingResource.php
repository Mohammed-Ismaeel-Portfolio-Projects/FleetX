<?php

namespace App\Http\Resources\Client;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client-safe shipment tracking payload
 * (C2.7-B / C2.7-C / C4.2-A3.2 / C4.2-A3.3 / C5.5-C2.2 / C5.5-C2.3 / C5.5-C2.4 / C5.5-C2.5).
 * Explicit allowlist only — never wholesale model or PlannedRouteCache serialization.
 * last_known_position is a three-field projection only (latitude, longitude, recorded_at).
 * location_freshness is derived from last_known_position.recorded_at only.
 * origin/destination.coordinates are hub-level endpoint coords (not street GPS).
 * route is sanitized planned geometry (coordinates only) or null — never Ops route payload.
 * No driver, vehicle, ETA, encoded polyline, provider, tracking history arrays, or updated_at-as-status-time.
 *
 * @mixin Shipment
 */
class ClientShipmentTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Shipment $shipment */
        $shipment = $this->resource;

        return [
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'status' => $this->when(
                $shipment->relationLoaded('status') && $shipment->status !== null,
                fn () => [
                    'id' => $shipment->status->id,
                    'name' => $shipment->status->name,
                    'type' => $shipment->status->type,
                ],
                null
            ),
            'planned_pickup_date' => $shipment->planned_pickup_date?->format('Y-m-d'),
            'planned_delivery_date' => $shipment->planned_delivery_date?->format('Y-m-d'),
            'actual_pickup_date' => $shipment->actual_pickup_date?->format('Y-m-d'),
            'actual_delivery_date' => $shipment->actual_delivery_date?->format('Y-m-d'),
            'origin' => [
                'city' => $shipment->pickup_city,
                'country' => $shipment->pickup_country,
                'company' => $shipment->pickup_company,
                'coordinates' => $this->endpointCoordinates($shipment->getAttribute('origin_coordinates')),
            ],
            'destination' => [
                'city' => $shipment->delivery_city,
                'country' => $shipment->delivery_country,
                'company' => $shipment->delivery_company,
                'coordinates' => $this->endpointCoordinates($shipment->getAttribute('destination_coordinates')),
            ],
            'customer_company' => $this->when(
                $shipment->relationLoaded('customerCompany') && $shipment->customerCompany !== null,
                fn () => [
                    'id' => $shipment->customerCompany->id,
                    'company_code' => $shipment->customerCompany->company_code,
                    'company_name' => $shipment->customerCompany->company_name,
                ],
                null
            ),
            'created_at' => $shipment->created_at?->format('Y-m-d H:i:s'),
            'milestones' => $this->derivedMilestones($shipment),
            'last_known_position' => $this->lastKnownPosition($shipment),
            'location_freshness' => $this->locationFreshness($shipment),
            'route' => $this->clientRoute($shipment),
        ];
    }

    /**
     * Sanitized planned route prepared by GetClientShipmentTrackingAction.
     *
     * @return array{coordinates: list<array{latitude: float, longitude: float}>}|null
     */
    private function clientRoute(Shipment $shipment): ?array
    {
        $raw = $shipment->getAttribute('client_route');

        if (! is_array($raw) || ! isset($raw['coordinates']) || ! is_array($raw['coordinates'])) {
            return null;
        }

        $coordinates = [];

        foreach ($raw['coordinates'] as $point) {
            $normalized = $this->endpointCoordinates($point);
            if ($normalized === null) {
                return null;
            }

            $coordinates[] = $normalized;
        }

        if (count($coordinates) < 2) {
            return null;
        }

        return ['coordinates' => $coordinates];
    }

    /**
     * Freshness prepared by GetClientShipmentTrackingAction from last_known_position.recorded_at.
     *
     * @return array{state: string, recorded_at: string|null, age_seconds: int}
     */
    private function locationFreshness(Shipment $shipment): array
    {
        $raw = $shipment->getAttribute('location_freshness');
        $allowed = ['fresh', 'stale', 'very_stale', 'unavailable'];

        if (! is_array($raw)
            || ! isset($raw['state'])
            || ! is_string($raw['state'])
            || ! in_array($raw['state'], $allowed, true)
            || ! array_key_exists('recorded_at', $raw)
            || ! array_key_exists('age_seconds', $raw)
        ) {
            return [
                'state' => 'unavailable',
                'recorded_at' => null,
                'age_seconds' => 0,
            ];
        }

        $recordedAt = $raw['recorded_at'];
        if ($recordedAt !== null && ! is_string($recordedAt)) {
            $recordedAt = null;
        }

        $age = $raw['age_seconds'];
        if (! is_int($age) && ! (is_numeric($age) && (int) $age == $age)) {
            $age = 0;
        } else {
            $age = (int) $age;
        }

        if ($age < 0) {
            $age = 0;
        }

        if ($raw['state'] === 'unavailable') {
            return [
                'state' => 'unavailable',
                'recorded_at' => null,
                'age_seconds' => 0,
            ];
        }

        if ($recordedAt === null || $recordedAt === '') {
            return [
                'state' => 'unavailable',
                'recorded_at' => null,
                'age_seconds' => 0,
            ];
        }

        return [
            'state' => $raw['state'],
            'recorded_at' => $recordedAt,
            'age_seconds' => $age,
        ];
    }

    /**
     * Hub endpoint coordinates prepared by GetClientShipmentTrackingAction.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    private function endpointCoordinates(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        if (! array_key_exists('latitude', $raw) || ! array_key_exists('longitude', $raw)) {
            return null;
        }

        $latitude = $raw['latitude'];
        $longitude = $raw['longitude'];

        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * Derived milestone view (C2.7-B option B) — date facts only, not status-transition history.
     *
     * @return list<array<string, mixed>>
     */
    private function derivedMilestones(Shipment $shipment): array
    {
        return [
            [
                'key' => 'created',
                'occurred_at' => $shipment->created_at?->format('Y-m-d H:i:s'),
            ],
            [
                'key' => 'pickup',
                'planned_at' => $shipment->planned_pickup_date?->format('Y-m-d'),
                'actual_at' => $shipment->actual_pickup_date?->format('Y-m-d'),
            ],
            [
                'key' => 'delivery',
                'planned_at' => $shipment->planned_delivery_date?->format('Y-m-d'),
                'actual_at' => $shipment->actual_delivery_date?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Strict three-field GPS projection prepared by GetClientShipmentTrackingAction.
     * Defense-in-depth: reject partial / out-of-range / non-finite values.
     *
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function lastKnownPosition(Shipment $shipment): ?array
    {
        $position = $shipment->getAttribute('last_known_position');

        if (! is_array($position)) {
            return null;
        }

        if (! array_key_exists('latitude', $position)
            || ! array_key_exists('longitude', $position)
            || ! array_key_exists('recorded_at', $position)
        ) {
            return null;
        }

        $latitude = $position['latitude'];
        $longitude = $position['longitude'];
        $recordedAt = $position['recorded_at'];

        if ($latitude === null || $longitude === null || $recordedAt === null || $recordedAt === '') {
            return null;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude) || ! is_string($recordedAt)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $recordedAt)) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => $recordedAt,
        ];
    }
}
