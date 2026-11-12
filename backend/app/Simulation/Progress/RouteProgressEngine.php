<?php

namespace App\Simulation\Progress;

use App\Models\Assignment;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * RouteProgressEngine
 *
 * Advances vehicles along the immutable planned route geometry from PlannedRouteCache.
 * Never plans or regenerates routes — read cache, advance, persist progress only.
 */
class RouteProgressEngine
{
    public const GEOMETRY_CACHE_PREFIX = 'simulation:route_geometry:';
    public const PROGRESS_CACHE_PREFIX = 'simulation:route_progress:';

    /** @var array<string, array<string, mixed>> In-process geometry memo */
    private array $geometryMemo = [];

    public function __construct(
        private PlannedRouteCache $plannedRouteCache,
    ) {}

    /**
     * Advance along planned geometry for one tick.
     *
     * @param float|null $maxDistanceTravelledM Absolute travelled cap (rest-stop clamp). Null = uncapped.
     * @return RouteProgressState|null Null when no usable geometry could be loaded
     */
    public function advance(
        Assignment $assignment,
        float $speedKmh,
        int $secondsElapsed = 60,
        ?float $maxDistanceTravelledM = null,
    ): ?RouteProgressState {
        $shipmentId = (string) $assignment->shipment_id;
        $vehicleId = (string) $assignment->vehicle_id;

        $geometry = $this->loadGeometry($shipmentId);
        if ($geometry === null) {
            return null;
        }

        $state = $this->loadProgress($shipmentId)
            ?? $this->createInitialState($shipmentId, $vehicleId, $geometry);

        if ($state->completed) {
            $state->lastTick = now()->toIso8601String();
            $this->persistProgress($state);
            return $state;
        }

        $distanceToTravel = ($speedKmh * 1000 / 3600) * $secondsElapsed;
        if ($maxDistanceTravelledM !== null) {
            $allowed = max(0.0, $maxDistanceTravelledM - $state->distanceTravelledM);
            $distanceToTravel = min($distanceToTravel, $allowed);
        }

        $previousSegment = $state->segmentIndex;

        $advanced = $this->advanceAlongGeometry($state, $geometry, $distanceToTravel);

        if ($advanced->segmentIndex !== $previousSegment) {
            Log::info('Segment completed', [
                'shipment_id' => $shipmentId,
                'vehicle_id' => $vehicleId,
                'completed_segment' => $previousSegment,
                'current_segment' => $advanced->segmentIndex,
            ]);
        }

        Log::info('Current segment', [
            'shipment_id' => $shipmentId,
            'vehicle_id' => $vehicleId,
            'segment_index' => $advanced->segmentIndex,
            'polyline_index' => $advanced->polylineIndex,
        ]);

        Log::info('Route progress %', [
            'shipment_id' => $shipmentId,
            'vehicle_id' => $vehicleId,
            'completion_percentage' => round($advanced->completionPercentage, 2),
            'distance_travelled_m' => round($advanced->distanceTravelledM, 1),
            'remaining_distance_m' => round($advanced->remainingDistanceM, 1),
        ]);

        if ($advanced->completed) {
            Log::info('Route completed', [
                'shipment_id' => $shipmentId,
                'vehicle_id' => $vehicleId,
                'route_id' => $advanced->routeId,
            ]);
        }

        $advanced->lastTick = now()->toIso8601String();
        $this->persistProgress($advanced);

        return $advanced;
    }

    public function getProgress(string $shipmentId): ?RouteProgressState
    {
        return $this->loadProgress($shipmentId);
    }

    public function isCompleted(string $shipmentId): bool
    {
        $state = $this->loadProgress($shipmentId);

        return $state !== null && $state->completed;
    }

    /**
     * Peek / ensure geometry is loaded (for diagnostics).
     *
     * @return array<string, mixed>|null
     */
    public function getGeometry(string $shipmentId): ?array
    {
        return $this->loadGeometry($shipmentId);
    }

    /**
     * @return array{
     *     route_id: string,
     *     provider: string,
     *     coordinates: list<array{latitude: float, longitude: float}>,
     *     segment_lengths_m: list<float>,
     *     cumulative_m: list<float>,
     *     total_distance_m: float
     * }|null
     */
    private function loadGeometry(string $shipmentId): ?array
    {
        $planned = $this->plannedRouteCache->get($shipmentId);
        if (!$this->plannedRouteCache->hasUsableRoute($planned)) {
            unset($this->geometryMemo[$shipmentId]);
            Log::warning('RouteProgressEngine: no immutable planned route in cache', [
                'shipment_id' => $shipmentId,
            ]);

            return null;
        }

        $plannedRouteId = (string) ($planned['api_payload']['route']['id'] ?? '');

        if (isset($this->geometryMemo[$shipmentId])) {
            $memoRouteId = (string) ($this->geometryMemo[$shipmentId]['route_id'] ?? '');
            if ($plannedRouteId === '' || $plannedRouteId === $memoRouteId) {
                return $this->geometryMemo[$shipmentId];
            }
            unset($this->geometryMemo[$shipmentId]);
        }

        $cached = Cache::get(self::GEOMETRY_CACHE_PREFIX . $shipmentId);
        if (
            is_array($cached)
            && !empty($cached['coordinates'])
            && isset($cached['total_distance_m'])
            && ($plannedRouteId === '' || $plannedRouteId === (string) ($cached['route_id'] ?? ''))
        ) {
            $this->geometryMemo[$shipmentId] = $cached;

            return $cached;
        }

        $coordinates = $this->extractCoordinates($planned);
        if ($coordinates === null || count($coordinates) < 2) {
            Log::warning('RouteProgressEngine: planned route missing coordinates', [
                'shipment_id' => $shipmentId,
            ]);

            return null;
        }

        $geometry = $this->buildGeometryPayload(
            shipmentId: $shipmentId,
            coordinates: $coordinates,
            routeId: $plannedRouteId !== '' ? $plannedRouteId : ('route-' . $shipmentId),
            provider: (string) ($planned['provider'] ?? 'graphhopper'),
        );

        // Tied to immutable planned route — cleared only via PlannedRouteCache::invalidate/replace.
        Cache::forever(self::GEOMETRY_CACHE_PREFIX . $shipmentId, $geometry);
        $this->geometryMemo[$shipmentId] = $geometry;

        Log::info('Route loaded', [
            'shipment_id' => $shipmentId,
            'route_id' => $geometry['route_id'],
            'provider' => $geometry['provider'],
            'coordinate_count' => count($geometry['coordinates']),
            'total_distance_m' => round($geometry['total_distance_m'], 1),
        ]);

        return $geometry;
    }

    /**
     * @param array<string, mixed>|null $planned
     * @return list<array{latitude: float, longitude: float}>|null
     */
    private function extractCoordinates(?array $planned): ?array
    {
        if ($planned === null) {
            return null;
        }

        $coords = $planned['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) {
            $coords = $planned['api_payload']['route']['geometry']['coordinates'] ?? null;
        }

        return $this->normalizeCoordinateList($coords);
    }

    /**
     * @param mixed $coords
     * @return list<array{latitude: float, longitude: float}>|null
     */
    private function normalizeCoordinateList(mixed $coords): ?array
    {
        if (!is_array($coords) || count($coords) < 2) {
            return null;
        }

        $normalized = [];
        foreach ($coords as $coord) {
            if (!is_array($coord)) {
                continue;
            }
            $lat = $coord['latitude'] ?? $coord['lat'] ?? null;
            $lng = $coord['longitude'] ?? $coord['lon'] ?? $coord['lng'] ?? null;
            if ($lat === null || $lng === null) {
                continue;
            }
            $normalized[] = [
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        return count($normalized) >= 2 ? $normalized : null;
    }

    /**
     * @param list<array{latitude: float, longitude: float}> $coordinates
     * @return array{
     *     route_id: string,
     *     provider: string,
     *     coordinates: list<array{latitude: float, longitude: float}>,
     *     segment_lengths_m: list<float>,
     *     cumulative_m: list<float>,
     *     total_distance_m: float
     * }
     */
    private function buildGeometryPayload(
        string $shipmentId,
        array $coordinates,
        string $routeId,
        string $provider,
    ): array {
        $segmentLengths = [];
        $cumulative = [0.0];
        $total = 0.0;

        for ($i = 0; $i < count($coordinates) - 1; $i++) {
            $len = $this->haversineMeters(
                $coordinates[$i]['latitude'],
                $coordinates[$i]['longitude'],
                $coordinates[$i + 1]['latitude'],
                $coordinates[$i + 1]['longitude'],
            );
            $segmentLengths[] = $len;
            $total += $len;
            $cumulative[] = $total;
        }

        return [
            'route_id' => $routeId !== '' ? $routeId : ('route-' . $shipmentId),
            'provider' => $provider,
            'coordinates' => $coordinates,
            'segment_lengths_m' => $segmentLengths,
            'cumulative_m' => $cumulative,
            'total_distance_m' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $geometry
     */
    private function createInitialState(string $shipmentId, string $vehicleId, array $geometry): RouteProgressState
    {
        $start = $geometry['coordinates'][0];
        $next = $geometry['coordinates'][1] ?? $start;

        return new RouteProgressState(
            routeId: (string) $geometry['route_id'],
            shipmentId: $shipmentId,
            vehicleId: $vehicleId,
            polylineIndex: 0,
            segmentIndex: 0,
            distanceTravelledM: 0.0,
            remainingDistanceM: (float) $geometry['total_distance_m'],
            completionPercentage: 0.0,
            currentLatitude: (float) $start['latitude'],
            currentLongitude: (float) $start['longitude'],
            heading: $this->bearingDegrees(
                (float) $start['latitude'],
                (float) $start['longitude'],
                (float) $next['latitude'],
                (float) $next['longitude'],
            ),
            completed: false,
            lastTick: now()->toIso8601String(),
            totalDistanceM: (float) $geometry['total_distance_m'],
            provider: (string) $geometry['provider'],
        );
    }

    /**
     * @param array<string, mixed> $geometry
     */
    private function advanceAlongGeometry(
        RouteProgressState $state,
        array $geometry,
        float $distanceToTravelM,
    ): RouteProgressState {
        $coords = $geometry['coordinates'];
        $segmentLengths = $geometry['segment_lengths_m'];
        $cumulative = $geometry['cumulative_m'];
        $total = (float) $geometry['total_distance_m'];

        if ($total <= 0 || count($coords) < 2 || $segmentLengths === []) {
            $end = $coords[count($coords) - 1] ?? ['latitude' => 0.0, 'longitude' => 0.0];
            $state->completed = true;
            $state->completionPercentage = 100.0;
            $state->remainingDistanceM = 0.0;
            $state->distanceTravelledM = $total;
            $state->currentLatitude = (float) $end['latitude'];
            $state->currentLongitude = (float) $end['longitude'];

            return $state;
        }

        $travelled = min($total, $state->distanceTravelledM + max(0.0, $distanceToTravelM));

        $seg = 0;
        $lastSeg = count($segmentLengths) - 1;
        while ($seg < $lastSeg && $travelled >= ($cumulative[$seg + 1] ?? $total)) {
            $seg++;
        }

        $segStartCum = (float) ($cumulative[$seg] ?? 0.0);
        $segLen = (float) ($segmentLengths[$seg] ?? 0.0);
        $into = $travelled - $segStartCum;
        $ratio = $segLen <= 0.0001 ? 1.0 : min(1.0, max(0.0, $into / $segLen));

        $a = $coords[$seg];
        $b = $coords[$seg + 1] ?? $a;
        $lat = $a['latitude'] + ($b['latitude'] - $a['latitude']) * $ratio;
        $lng = $a['longitude'] + ($b['longitude'] - $a['longitude']) * $ratio;
        $heading = $this->bearingDegrees($a['latitude'], $a['longitude'], $b['latitude'], $b['longitude']);

        $completed = $travelled >= ($total - 0.5);
        if ($completed) {
            $end = $coords[count($coords) - 1];
            $lat = (float) $end['latitude'];
            $lng = (float) $end['longitude'];
            $travelled = $total;
            $seg = $lastSeg;
            $ratio = 1.0;
        }

        $state->segmentIndex = $seg;
        $state->polylineIndex = $ratio >= 1.0 ? min($seg + 1, count($coords) - 1) : $seg;
        $state->distanceTravelledM = $travelled;
        $state->remainingDistanceM = max(0.0, $total - $travelled);
        $state->completionPercentage = $total > 0 ? min(100.0, ($travelled / $total) * 100.0) : 100.0;
        $state->currentLatitude = $lat;
        $state->currentLongitude = $lng;
        $state->heading = $heading;
        $state->completed = $completed;
        $state->totalDistanceM = $total;
        $state->routeId = (string) $geometry['route_id'];
        $state->provider = (string) $geometry['provider'];

        return $state;
    }

    private function loadProgress(string $shipmentId): ?RouteProgressState
    {
        $payload = Cache::get(self::PROGRESS_CACHE_PREFIX . $shipmentId);
        if (!is_array($payload)) {
            return null;
        }

        return RouteProgressState::fromArray($payload);
    }

    private function persistProgress(RouteProgressState $state): void
    {
        Cache::forever(
            self::PROGRESS_CACHE_PREFIX . $state->shipmentId,
            $state->toArray(),
        );
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
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

    private function bearingDegrees(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLon = deg2rad($lon2 - $lon1);

        $x = sin($deltaLon) * cos($lat2Rad);
        $y = cos($lat1Rad) * sin($lat2Rad)
            - sin($lat1Rad) * cos($lat2Rad) * cos($deltaLon);

        $bearing = rad2deg(atan2($x, $y));

        return fmod($bearing + 360.0, 360.0);
    }
}
