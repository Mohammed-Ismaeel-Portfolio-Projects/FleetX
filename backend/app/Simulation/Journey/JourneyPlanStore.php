<?php

namespace App\Simulation\Journey;

use Illuminate\Support\Facades\Cache;

/**
 * Cache persistence for JourneyPlan (idempotent ensure — no duplicate plans).
 */
final class JourneyPlanStore
{
    public const CACHE_PREFIX = 'simulation:journey:';

    private const CACHE_TTL_SECONDS = 86400;

    public function get(string $shipmentId): ?JourneyPlan
    {
        $data = Cache::get(self::CACHE_PREFIX.$shipmentId);
        if (! is_array($data)) {
            return null;
        }

        return JourneyPlan::fromArray($data);
    }

    public function put(JourneyPlan $plan): void
    {
        Cache::put(
            self::CACHE_PREFIX.$plan->shipmentId,
            $plan->toArray(),
            self::CACHE_TTL_SECONDS
        );
    }

    public function forget(string $shipmentId): void
    {
        Cache::forget(self::CACHE_PREFIX.$shipmentId);
    }
}
