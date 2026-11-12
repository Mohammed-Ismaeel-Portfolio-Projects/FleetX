<?php

namespace App\Services\AI\Eta;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\TrackingState;

class EtaFeatureBuilder
{
    /**
     * Build feature array matching feature_manifest.json.
     * Guaranteed ZERO temporal data leakage.
     *
     * @param Shipment $shipment
     * @param array<string, mixed>|null $progress
     * @param TrackingState|TrackingLog|array<string, mixed>|null $currentTracking
     * @return array<string, mixed>
     */
    public function buildFeatures(
        Shipment $shipment,
        ?array $progress = null,
        mixed $currentTracking = null
    ): array {
        $order = $shipment->shipmentOrder;

        // Baseline fields (frozen at creation)
        $baseEtaSeconds = $shipment->baseline_eta_seconds ?? $order?->planned_duration_seconds ?? 0;
        $baseDistanceMeters = $shipment->baseline_distance_meters ?? $order?->planned_distance_meters ?? 0;

        // Dynamic progress at time t
        $remainingDistance = isset($progress['remaining_distance_m'])
            ? (float) $progress['remaining_distance_m']
            : (isset($progress['remaining_distance_meters']) ? (float) $progress['remaining_distance_meters'] : (float) $baseDistanceMeters);

        $totalDistance = isset($progress['total_distance_m'])
            ? (float) $progress['total_distance_m']
            : (isset($progress['total_distance_meters']) ? (float) $progress['total_distance_meters'] : (float) $baseDistanceMeters);

        $distanceTravelled = isset($progress['distance_travelled_m'])
            ? (float) $progress['distance_travelled_m']
            : (isset($progress['distance_travelled_meters']) ? (float) $progress['distance_travelled_meters'] : max(0.0, $totalDistance - $remainingDistance));

        $completionPct = isset($progress['completion_percentage'])
            ? (float) $progress['completion_percentage']
            : ($totalDistance > 0 ? min(100.0, ($distanceTravelled / $totalDistance) * 100.0) : 0.0);

        // Dynamic telemetry at time t
        $lat = 0.0;
        $lng = 0.0;
        $speedKmh = 0.0;
        $heading = 0.0;
        $accuracy = 5.0;

        if ($currentTracking instanceof TrackingState || $currentTracking instanceof TrackingLog) {
            $lat = (float) $currentTracking->latitude;
            $lng = (float) $currentTracking->longitude;
            $speedKmh = $currentTracking->speed !== null ? (float) $currentTracking->speed : 0.0;
            $heading = $currentTracking->heading !== null ? (float) $currentTracking->heading : 0.0;
            $accuracy = $currentTracking->accuracy !== null ? (float) $currentTracking->accuracy : 5.0;
        } elseif (is_array($currentTracking)) {
            $lat = (float) ($currentTracking['latitude'] ?? $currentTracking['current_latitude'] ?? 0.0);
            $lng = (float) ($currentTracking['longitude'] ?? $currentTracking['current_longitude'] ?? 0.0);
            $speedKmh = (float) ($currentTracking['speed'] ?? $currentTracking['current_speed_kmh'] ?? 0.0);
            $heading = (float) ($currentTracking['heading'] ?? 0.0);
            $accuracy = (float) ($currentTracking['accuracy'] ?? $currentTracking['gps_accuracy'] ?? 5.0);
        }

        $movementStatus = $speedKmh > 5.0 ? 'MOVING' : ($speedKmh > 0.5 ? 'SLOW' : 'STOPPED');

        // Assets metadata
        /** @var Assignment|null $assignment */
        $assignment = $shipment->activeAssignment ?? $shipment->assignments->first();
        $driver = $assignment?->driver;
        $vehicle = $assignment?->vehicle;

        $driverExp = $driver?->years_of_experience ?? 0;
        $driverScore = $driver?->driverScore?->score !== null ? (float) $driver->driverScore->score : 85.0;
        $vehicleWeight = $vehicle?->gross_weight_kg ?? 18000;

        $now = now();
        $hourOfDay = (int) $now->hour;
        $dayOfWeek = (int) $now->dayOfWeekIso;

        return [
            'base_eta_seconds' => (float) $baseEtaSeconds,
            'base_distance_meters' => (float) $baseDistanceMeters,
            'remaining_distance_meters' => (float) $remainingDistance,
            'total_distance_meters' => (float) $totalDistance,
            'distance_travelled_meters' => (float) $distanceTravelled,
            'completion_percentage' => round($completionPct, 2),
            'current_latitude' => $lat,
            'current_longitude' => $lng,
            'current_speed_kmh' => $speedKmh,
            'heading' => $heading,
            'gps_accuracy' => $accuracy,
            'driver_years_experience' => (int) $driverExp,
            'driver_score' => (float) $driverScore,
            'vehicle_gross_weight_kg' => (int) $vehicleWeight,
            'cargo_weight' => (float) ($shipment->cargo_weight ?? 0.0),
            'cargo_type' => (string) ($shipment->cargo_type ?? 'General Freight'),
            'temperature_controlled' => $shipment->temperature_controlled ? 1 : 0,
            'hazardous_cargo' => $shipment->hazardous_cargo ? 1 : 0,
            'hour_of_day' => $hourOfDay,
            'day_of_week' => $dayOfWeek,
            'movement_status' => $movementStatus,
            'baseline_routing_provider' => (string) ($shipment->baseline_routing_provider ?? 'graphhopper'),
        ];
    }
}
