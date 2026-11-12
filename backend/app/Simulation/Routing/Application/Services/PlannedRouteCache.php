<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Progress\RouteProgressEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PlannedRouteCache
 *
 * Single source of truth for the active planned/rerouted route per shipment.
 * Routes are immutable for the lifetime of an active shipment unless an
 * authorized replace occurs (auto-reroute, explicit recalculate, destination change).
 */
class PlannedRouteCache
{
    private function key(string $shipmentId): string
    {
        return 'routing:planned_route:' . $shipmentId;
    }

    public function get(string $shipmentId): ?array
    {
        $payload = Cache::get($this->key($shipmentId));

        return is_array($payload) ? $payload : null;
    }

    public function has(string $shipmentId): bool
    {
        return $this->hasUsableRoute($this->get($shipmentId));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function hasUsableRoute(?array $payload): bool
    {
        if ($payload === null) {
            return false;
        }

        $coords = $payload['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) {
            $coords = $payload['api_payload']['route']['geometry']['coordinates'] ?? null;
        }

        return is_array($coords)
            && count($coords) >= 2
            && !empty($payload['api_payload']['route']);
    }

    /**
     * Persist a planned route only when none exists. Silent no-op if already frozen.
     *
     * @return bool True when a new route was stored
     */
    public function storeIfAbsent(
        string $shipmentId,
        array $coordinates,
        array $destination,
        array $origin,
        array $apiPayload,
        string $provider = 'graphhopper',
        bool $isReroute = false,
    ): bool {
        if ($this->has($shipmentId)) {
            Log::info('PlannedRouteCache: refusing overwrite of immutable planned route', [
                'shipment_id' => $shipmentId,
                'existing_route_id' => $this->get($shipmentId)['api_payload']['route']['id'] ?? null,
            ]);

            return false;
        }

        $this->write($shipmentId, $coordinates, $destination, $origin, $apiPayload, $provider, $isReroute);

        return true;
    }

    /**
     * Authorized replacement: clears derived simulation caches, then stores exactly one new route.
     */
    public function replace(
        string $shipmentId,
        array $coordinates,
        array $destination,
        array $origin,
        array $apiPayload,
        string $provider = 'graphhopper',
        bool $isReroute = false,
    ): void {
        $this->forgetDerivedCaches($shipmentId);
        Cache::forget($this->key($shipmentId));

        $this->write($shipmentId, $coordinates, $destination, $origin, $apiPayload, $provider, $isReroute);
    }

    /**
     * @deprecated Use storeIfAbsent() or replace(). Kept for callers that expect rememberRoute().
     */
    public function rememberRoute(
        string $shipmentId,
        array $coordinates,
        array $destination,
        array $origin,
        array $apiPayload,
        string $provider = 'graphhopper',
        bool $isReroute = false,
    ): void {
        if ($isReroute) {
            $this->replace($shipmentId, $coordinates, $destination, $origin, $apiPayload, $provider, true);

            return;
        }

        $this->storeIfAbsent($shipmentId, $coordinates, $destination, $origin, $apiPayload, $provider, false);
    }

    public function updateDeviationStatus(
        string $shipmentId,
        string $routeStatus,
        float $deviationDistance,
    ): void {
        $existing = $this->get($shipmentId);
        if ($existing === null) {
            return;
        }

        // Metadata-only update; route geometry / id remain immutable.
        $existing['route_status'] = $routeStatus;
        $existing['deviation_distance'] = $deviationDistance;
        $existing['updated_at'] = now()->toIso8601String();
        Cache::forever($this->key($shipmentId), $existing);
    }

    public function markRerouted(string $shipmentId): void
    {
        $existing = $this->get($shipmentId);
        if ($existing === null) {
            return;
        }

        $existing['last_rerouted_at'] = now()->toIso8601String();
        $existing['route_status'] = 'ON_ROUTE';
        $existing['deviation_distance'] = 0;
        $existing['updated_at'] = now()->toIso8601String();
        Cache::forever($this->key($shipmentId), $existing);
    }

    public function canAutoReroute(string $shipmentId): bool
    {
        $existing = $this->get($shipmentId);
        if ($existing === null) {
            return true;
        }

        $last = $existing['last_rerouted_at'] ?? null;
        if ($last === null) {
            return true;
        }

        $cooldown = (int) config('routing.deviation.reroute_cooldown_seconds', 120);
        $lastAt = strtotime((string) $last);

        return $lastAt === false || (time() - $lastAt) >= $cooldown;
    }

    /**
     * Drop planned route and all derived simulation geometry/progress for this shipment.
     */
    public function invalidate(string $shipmentId): void
    {
        $this->forgetDerivedCaches($shipmentId);
        Cache::forget($this->key($shipmentId));

        Log::info('PlannedRouteCache: invalidated planned route', [
            'shipment_id' => $shipmentId,
        ]);
    }

    public function forget(string $shipmentId): void
    {
        $this->invalidate($shipmentId);
    }

    private function forgetDerivedCaches(string $shipmentId): void
    {
        Cache::forget(RouteProgressEngine::GEOMETRY_CACHE_PREFIX . $shipmentId);
        Cache::forget(RouteProgressEngine::PROGRESS_CACHE_PREFIX . $shipmentId);
        Cache::forget(\App\Simulation\Journey\JourneyPlanStore::CACHE_PREFIX . $shipmentId);
    }

    private function write(
        string $shipmentId,
        array $coordinates,
        array $destination,
        array $origin,
        array $apiPayload,
        string $provider,
        bool $isReroute,
    ): void {
        $payload = [
            'shipment_id' => $shipmentId,
            'coordinates' => $coordinates,
            'destination' => $destination,
            'origin' => $origin,
            'api_payload' => $apiPayload,
            'provider' => $provider,
            'is_reroute' => $isReroute,
            'delivery_city' => $apiPayload['metadata']['delivery_city'] ?? null,
            'pickup_city' => $apiPayload['metadata']['pickup_city'] ?? null,
            'route_status' => 'ON_ROUTE',
            'deviation_distance' => 0,
            'frozen_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        // Forever while shipment is active; removed via invalidate() on complete/reroute.
        Cache::forever($this->key($shipmentId), $payload);

        Log::info('PlannedRouteCache: stored planned route', [
            'shipment_id' => $shipmentId,
            'route_id' => $apiPayload['route']['id'] ?? null,
            'provider' => $provider,
            'is_reroute' => $isReroute,
            'coordinate_count' => count($coordinates),
        ]);
    }
}
