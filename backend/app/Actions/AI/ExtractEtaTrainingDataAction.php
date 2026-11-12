<?php

namespace App\Actions\AI;

use App\Actions\BaseAction;
use App\Core\ApiResult;
use App\Models\EtaObservationDatasetSample;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Extract historical ETA observation samples for AI residual model training (Phase 2).
 * Strictly enforces temporal data leakage boundaries.
 */
class ExtractEtaTrainingDataAction extends BaseAction
{
    public function __construct(
        private readonly ?PlannedRouteCache $plannedRouteCache = null
    ) {}

    public function execute(
        ?string $shipmentId = null,
        ?Carbon $fromDate = null,
        ?Carbon $toDate = null,
        int $chunkSize = 50
    ): ApiResult {
        $query = Shipment::query()
            ->whereNotNull('actual_delivery_at')
            ->with([
                'shipmentOrder',
                'assignments' => fn ($q) => $q->orderBy('created_at', 'desc'),
                'assignments.driver.driverScore',
                'assignments.vehicle',
                'trackingLogs' => fn ($q) => $q->orderBy('recorded_at', 'asc'),
            ]);

        if ($shipmentId !== null) {
            $query->where('id', $shipmentId);
        }

        if ($fromDate !== null) {
            $query->where('actual_delivery_at', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where('actual_delivery_at', '<=', $toDate);
        }

        $shipmentsProcessed = 0;
        $samplesExtracted = 0;
        $samplesSkipped = 0;
        $skippedReasons = [];

        $routeCache = $this->plannedRouteCache ?? app(PlannedRouteCache::class);
        $cruiseSpeedKmh = (float) config('simulation.rest.assumed_cruise_speed_kmh', 80.0);

        $query->chunk($chunkSize, function ($shipments) use (
            $routeCache,
            $cruiseSpeedKmh,
            &$shipmentsProcessed,
            &$samplesExtracted,
            &$samplesSkipped,
            &$skippedReasons
        ) {
            /** @var Shipment $shipment */
            foreach ($shipments as $shipment) {
                $shipmentsProcessed++;

                $deliveryAt = $shipment->actual_delivery_at;
                if ($deliveryAt === null) {
                    $samplesSkipped++;
                    $skippedReasons[] = "Shipment {$shipment->id}: missing actual_delivery_at";
                    continue;
                }

                $baseEtaSeconds = $shipment->baseline_eta_seconds ?? $shipment->shipmentOrder?->planned_duration_seconds;
                $baseDistanceMeters = $shipment->baseline_distance_meters ?? $shipment->shipmentOrder?->planned_distance_meters;

                if ($baseEtaSeconds === null || $baseDistanceMeters === null) {
                    $samplesSkipped++;
                    $skippedReasons[] = "Shipment {$shipment->id}: missing baseline ETA or distance";
                    continue;
                }

                // Resolve route geometry for deterministic map-matching projection
                $geometryCoords = $this->resolveRouteCoordinates($shipment, $routeCache);

                // Primary assignment & assets
                $assignment = $shipment->assignments->first();
                $driver = $assignment?->driver;
                $vehicle = $assignment?->vehicle;

                $driverYears = $driver?->years_of_experience;
                $driverScoreVal = $driver?->driverScore?->score;
                $vehicleWeight = $vehicle?->gross_weight_kg;

                $pickupAt = $shipment->actual_pickup_at;
                $actualTotalDuration = ($pickupAt !== null && $deliveryAt > $pickupAt)
                    ? (int) $deliveryAt->diffInSeconds($pickupAt)
                    : (int) $deliveryAt->diffInSeconds($shipment->created_at);

                $logs = $shipment->trackingLogs;
                if ($logs->isEmpty()) {
                    $samplesSkipped++;
                    $skippedReasons[] = "Shipment {$shipment->id}: no tracking logs recorded";
                    continue;
                }

                /** @var TrackingLog $log */
                foreach ($logs as $log) {
                    $obsTime = $log->recorded_at;
                    if ($obsTime === null) {
                        $samplesSkipped++;
                        continue;
                    }

                    // STRICT LEAKAGE RULE: Observation time MUST be strictly before actual_delivery_at
                    if ($obsTime->timestamp >= $deliveryAt->timestamp) {
                        $samplesSkipped++;
                        continue;
                    }

                    // Compute Target Variables
                    $actualRemainingSeconds = (float) ($deliveryAt->timestamp - $obsTime->timestamp);
                    if ($actualRemainingSeconds <= 0) {
                        $samplesSkipped++;
                        continue;
                    }

                    // Compute Route Progress Features at observation time t
                    $progress = $this->calculateProgressAtObservation(
                        (float) $log->latitude,
                        (float) $log->longitude,
                        $geometryCoords,
                        (float) $baseDistanceMeters,
                        $shipment
                    );

                    $remainingDistance = $progress['remaining_distance_meters'];
                    $baselineRemainingSeconds = ($remainingDistance / 1000.0) / $cruiseSpeedKmh * 3600.0;
                    $residualSeconds = $actualRemainingSeconds - $baselineRemainingSeconds;

                    $speedKmh = $log->speed !== null ? (float) $log->speed : null;
                    $movementStatus = $speedKmh === null
                        ? null
                        : ($speedKmh > 5.0 ? 'MOVING' : ($speedKmh > 0.5 ? 'SLOW' : 'STOPPED'));

                    EtaObservationDatasetSample::updateOrCreate(
                        [
                            'shipment_id' => $shipment->id,
                            'observation_time' => $obsTime,
                        ],
                        [
                            'assignment_id' => $assignment?->id,
                            'tracking_log_id' => $log->id,
                            'base_eta_seconds' => $baseEtaSeconds,
                            'base_distance_meters' => $baseDistanceMeters,
                            'baseline_routing_provider' => $shipment->baseline_routing_provider ?? 'graphhopper',
                            'remaining_distance_meters' => $remainingDistance,
                            'total_distance_meters' => $progress['total_distance_meters'],
                            'distance_travelled_meters' => $progress['distance_travelled_meters'],
                            'completion_percentage' => $progress['completion_percentage'],
                            'segment_index' => $progress['segment_index'],
                            'polyline_index' => $progress['polyline_index'],
                            'current_latitude' => $log->latitude,
                            'current_longitude' => $log->longitude,
                            'current_speed_kmh' => $log->speed,
                            'heading' => $log->heading,
                            'gps_accuracy' => $log->accuracy,
                            'movement_status' => $movementStatus,
                            'driver_id' => $driver?->id,
                            'driver_years_experience' => $driverYears,
                            'driver_score' => $driverScoreVal,
                            'vehicle_id' => $vehicle?->id,
                            'vehicle_gross_weight_kg' => $vehicleWeight,
                            'cargo_weight' => $shipment->cargo_weight,
                            'cargo_type' => $shipment->cargo_type,
                            'temperature_controlled' => (bool) $shipment->temperature_controlled,
                            'hazardous_cargo' => (bool) $shipment->hazardous_cargo,
                            'hour_of_day' => (int) $obsTime->hour,
                            'day_of_week' => (int) $obsTime->dayOfWeekIso,
                            'actual_total_duration_seconds' => $actualTotalDuration,
                            'actual_remaining_seconds' => $actualRemainingSeconds,
                            'baseline_remaining_seconds' => $baselineRemainingSeconds,
                            'residual_seconds' => $residualSeconds,
                        ]
                    );

                    $samplesExtracted++;
                }
            }
        });

        $resultPayload = [
            'shipments_processed' => $shipmentsProcessed,
            'samples_extracted' => $samplesExtracted,
            'samples_skipped' => $samplesSkipped,
            'skipped_reasons_sample' => array_slice($skippedReasons, 0, 10),
        ];

        Log::info('ExtractEtaTrainingDataAction complete', $resultPayload);

        return ApiResult::success($resultPayload, 'ETA training dataset extraction complete');
    }

    /**
     * Resolve polyline geometry coordinates [[lat, lng], ...] from PlannedRouteCache or ShipmentOrder.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    private function resolveRouteCoordinates(Shipment $shipment, PlannedRouteCache $routeCache): ?array
    {
        $cached = $routeCache->get((string) $shipment->id);
        $coords = $cached['coordinates'] ?? null;

        if (! is_array($coords) || count($coords) < 2) {
            $order = $shipment->shipmentOrder;
            if ($order !== null && is_array($order->planned_route_geometry) && count($order->planned_route_geometry) >= 2) {
                $coords = $order->planned_route_geometry;
            }
        }

        if (! is_array($coords) || count($coords) < 2) {
            return null;
        }

        $normalized = [];
        foreach ($coords as $pt) {
            if (! is_array($pt)) {
                continue;
            }

            $lat = $pt['latitude'] ?? $pt['lat'] ?? $pt[0] ?? null;
            $lng = $pt['longitude'] ?? $pt['lon'] ?? $pt['lng'] ?? $pt[1] ?? null;

            if ($lat !== null && $lng !== null) {
                $normalized[] = [(float) $lat, (float) $lng];
            }
        }

        return count($normalized) >= 2 ? $normalized : null;
    }

    /**
     * Map-match observation GPS (obsLat, obsLng) to polyline geometry or fallback to destination distance.
     *
     * @param list<array{0: float, 1: float}>|null $geometryCoords
     * @return array{
     *     total_distance_meters: float,
     *     distance_travelled_meters: float,
     *     remaining_distance_meters: float,
     *     completion_percentage: float,
     *     segment_index: ?int,
     *     polyline_index: ?int
     * }
     */
    private function calculateProgressAtObservation(
        float $obsLat,
        float $obsLng,
        ?array $geometryCoords,
        float $baseDistanceMeters,
        Shipment $shipment
    ): array {
        if ($geometryCoords !== null && count($geometryCoords) >= 2) {
            return $this->projectOntoPolyline($obsLat, $obsLng, $geometryCoords);
        }

        // Fallback: Haversine distance from observation to shipment delivery location
        $destLat = null;
        $destLng = null;

        if ($shipment->shipmentOrder !== null) {
            $destLat = $shipment->shipmentOrder->delivery_latitude;
            $destLng = $shipment->shipmentOrder->delivery_longitude;
        }

        if ($destLat !== null && $destLng !== null) {
            $remMeters = $this->haversine((float) $obsLat, (float) $obsLng, (float) $destLat, (float) $destLng);
            $totalDist = max($baseDistanceMeters, $remMeters);
            $travelled = max(0.0, $totalDist - $remMeters);
            $pct = $totalDist > 0 ? min(100.0, ($travelled / $totalDist) * 100.0) : 0.0;

            return [
                'total_distance_meters' => $totalDist,
                'distance_travelled_meters' => $travelled,
                'remaining_distance_meters' => $remMeters,
                'completion_percentage' => round($pct, 2),
                'segment_index' => null,
                'polyline_index' => null,
            ];
        }

        // Final safe fallback
        return [
            'total_distance_meters' => $baseDistanceMeters,
            'distance_travelled_meters' => 0.0,
            'remaining_distance_meters' => $baseDistanceMeters,
            'completion_percentage' => 0.0,
            'segment_index' => null,
            'polyline_index' => null,
        ];
    }

    /**
     * Deterministic polyline projection.
     *
     * @param list<array{0: float, 1: float}> $polyline
     * @return array{
     *     total_distance_meters: float,
     *     distance_travelled_meters: float,
     *     remaining_distance_meters: float,
     *     completion_percentage: float,
     *     segment_index: int,
     *     polyline_index: int
     * }
     */
    private function projectOntoPolyline(float $obsLat, float $obsLng, array $polyline): array
    {
        $segmentLengths = [];
        $cumulative = [0.0];
        $totalDistance = 0.0;

        for ($i = 0; $i < count($polyline) - 1; $i++) {
            $p1 = $polyline[$i];
            $p2 = $polyline[$i + 1];
            $len = $this->haversine($p1[0], $p1[1], $p2[0], $p2[1]);
            $segmentLengths[] = $len;
            $totalDistance += $len;
            $cumulative[] = $totalDistance;
        }

        if ($totalDistance <= 0) {
            return [
                'total_distance_meters' => 0.0,
                'distance_travelled_meters' => 0.0,
                'remaining_distance_meters' => 0.0,
                'completion_percentage' => 100.0,
                'segment_index' => 0,
                'polyline_index' => count($polyline) - 1,
            ];
        }

        $minDist = PHP_FLOAT_MAX;
        $closestSegment = 0;
        $closestFraction = 0.0;

        for ($i = 0; $i < count($polyline) - 1; $i++) {
            $p1 = $polyline[$i];
            $p2 = $polyline[$i + 1];

            [$dist, $fraction] = $this->pointToSegmentDistance($obsLat, $obsLng, $p1[0], $p1[1], $p2[0], $p2[1]);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestSegment = $i;
                $closestFraction = $fraction;
            }
        }

        $travelled = $cumulative[$closestSegment] + ($segmentLengths[$closestSegment] * $closestFraction);
        $travelled = min($totalDistance, max(0.0, $travelled));
        $remaining = max(0.0, $totalDistance - $travelled);
        $pct = min(100.0, ($travelled / $totalDistance) * 100.0);

        return [
            'total_distance_meters' => round($totalDistance, 2),
            'distance_travelled_meters' => round($travelled, 2),
            'remaining_distance_meters' => round($remaining, 2),
            'completion_percentage' => round($pct, 2),
            'segment_index' => $closestSegment,
            'polyline_index' => $closestFraction >= 1.0 ? min($closestSegment + 1, count($polyline) - 1) : $closestSegment,
        ];
    }

    /**
     * @return array{0: float, 1: float} Distance in meters and fraction along segment [0..1]
     */
    private function pointToSegmentDistance(
        float $px,
        float $py,
        float $x1,
        float $y1,
        float $x2,
        float $y2
    ): array {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;

        if (abs($dx) < 1e-9 && abs($dy) < 1e-9) {
            return [$this->haversine($px, $py, $x1, $y1), 0.0];
        }

        $t = (($px - $x1) * $dx + ($py - $y1) * $dy) / ($dx * $dx + $dy * $dy);
        $t = max(0.0, min(1.0, $t));

        $projX = $x1 + $t * $dx;
        $projY = $y1 + $t * $dy;

        return [$this->haversine($px, $py, $projX, $projY), $t];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLon / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
