<?php

namespace App\Actions\Shipment;

use App\Models\Assignment;
use App\Models\Shipment;
use App\Simulation\Journey\JourneyPlanStore;
use App\Simulation\Lifecycle\ShipmentLifecycleEngine;
use App\Simulation\Progress\RouteProgressEngine;
use App\Support\EngineEnrollmentEligibility;
use App\Support\EngineEnrollmentStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only Engine operational snapshot for Dispatcher visibility (C2.9-C2.8 Step 6).
 *
 * Does not mutate Engine state, Shipment.status_id, or history.
 * Assembles existing cache + assignment enrollment fields only.
 */
final class BuildShipmentEngineOpsReadModelAction
{
    public function __construct(
        private readonly JourneyPlanStore $journeyStore = new JourneyPlanStore(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Shipment $shipment): array
    {
        $shipmentId = (string) $shipment->id;
        $assignment = $shipment->relationLoaded('activeAssignment')
            ? $shipment->activeAssignment
            : $shipment->activeAssignment()->with(['status', 'driver', 'vehicle'])->first();

        // Ensure eligibility sees the same shipment status already loaded on the parent.
        if ($assignment !== null) {
            $assignment->setRelation('shipment', $shipment);
        }

        $enrollmentStatus = $assignment?->engine_enrollment_status;
        $enrolledAt = $assignment?->engine_enrolled_at;
        $simulationEligible = EngineEnrollmentStatus::isSimulationEligible(
            is_string($enrollmentStatus) ? $enrollmentStatus : null
        );

        $lifecycle = Cache::get(ShipmentLifecycleEngine::CACHE_PREFIX.$shipmentId);
        $lifecycle = is_array($lifecycle) ? $lifecycle : null;

        $progress = Cache::get(RouteProgressEngine::PROGRESS_CACHE_PREFIX.$shipmentId);
        $progress = is_array($progress) ? $progress : null;

        $journey = $this->journeyStore->get($shipmentId);
        $journeyArr = $journey?->toArray();

        $available = $lifecycle !== null || $progress !== null || $journeyArr !== null;

        $currentStage = is_array($lifecycle) ? ($lifecycle['current_stage'] ?? null) : null;
        $isResting = (bool) (
            ($lifecycle['is_resting'] ?? false)
            || ($journeyArr['is_resting'] ?? false)
        );

        $rest = $this->buildRestBlock($journeyArr, $progress, $isResting);
        $route = $this->buildRouteBlock($progress);
        $eta = $this->buildEtaBlock($shipment, $journeyArr, $progress);

        $eligibility = EngineEnrollmentEligibility::assess($assignment);

        return [
            'available' => $available,
            'simulation_eligible' => $simulationEligible,
            'enrollment' => [
                'status' => $enrollmentStatus,
                'enrolled_at' => $enrolledAt?->toIso8601String(),
                'label' => $this->enrollmentLabel($assignment, $enrollmentStatus),
                'can_enroll' => $eligibility['can_enroll'],
                'already_enrolled' => $eligibility['already_enrolled'],
                'enrollment_closed' => $eligibility['enrollment_closed'],
                'block_reason' => $eligibility['block_reason'],
                'assignment_id' => $assignment?->id,
            ],
            'canonical_status' => $shipment->relationLoaded('status')
                ? ($shipment->status?->name)
                : null,
            'operational_stage' => $currentStage,
            'previous_operational_stage' => is_array($lifecycle) ? ($lifecycle['previous_stage'] ?? null) : null,
            'transition_reason' => is_array($lifecycle) ? ($lifecycle['transition_reason'] ?? null) : null,
            'is_resting' => $isResting,
            'pickup_geofence_reached' => (bool) ($lifecycle['pickup_geofence_reached'] ?? false),
            'delivery_geofence_reached' => (bool) ($lifecycle['delivery_geofence_reached'] ?? false),
            'driving_since_rest_m' => isset($lifecycle['driving_since_rest_m'])
                ? (float) $lifecycle['driving_since_rest_m']
                : (isset($journeyArr['driving_since_rest_m']) ? (float) $journeyArr['driving_since_rest_m'] : null),
            'next_rest_offset_m' => isset($lifecycle['next_rest_offset_m'])
                ? (float) $lifecycle['next_rest_offset_m']
                : null,
            'engine_updated_at' => $lifecycle['timestamp']
                ?? $progress['last_tick']
                ?? null,
            'route_progress' => $route,
            'rest' => $rest,
            'eta' => $eta,
            'journey' => $journeyArr === null ? null : [
                'total_distance_m' => $journeyArr['total_distance_m'] ?? null,
                'max_driving_distance_m' => $journeyArr['max_driving_distance_m'] ?? null,
                'rest_duration_seconds' => $journeyArr['rest_duration_seconds'] ?? null,
                'estimated_travel_seconds' => $journeyArr['estimated_travel_seconds'] ?? null,
                'rest_stop_count' => isset($journeyArr['rest_stops']) && is_array($journeyArr['rest_stops'])
                    ? count($journeyArr['rest_stops'])
                    : 0,
                'planned_at' => $journeyArr['planned_at'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $journey
     * @param array<string, mixed>|null $progress
     * @return array<string, mixed>
     */
    private function buildRestBlock(?array $journey, ?array $progress, bool $isResting): array
    {
        if ($journey === null) {
            return [
                'active' => false,
                'message' => 'No journey plan available',
            ];
        }

        $stops = is_array($journey['rest_stops'] ?? null) ? $journey['rest_stops'] : [];
        if ($stops === []) {
            return [
                'active' => false,
                'message' => 'No planned rest stops for this route',
                'rest_stop_count' => 0,
            ];
        }

        $activeId = $journey['active_rest_stop_id'] ?? null;
        $active = null;
        $next = null;
        foreach ($stops as $stop) {
            if (! is_array($stop)) {
                continue;
            }
            if ($activeId !== null && ($stop['id'] ?? null) === $activeId) {
                $active = $stop;
            }
            if ($next === null && ($stop['status'] ?? '') !== 'completed') {
                $next = $stop;
            }
        }

        $travelled = isset($progress['distance_travelled_m']) ? (float) $progress['distance_travelled_m'] : null;
        $distanceToNext = null;
        if ($next !== null && $travelled !== null) {
            $distanceToNext = max(0.0, (float) $next['route_offset_m'] - $travelled);
        }

        $remainingRestSeconds = null;
        if ($isResting && $active !== null) {
            $started = $active['rest_started_at'] ?? $journey['rest_started_at'] ?? null;
            $duration = (int) ($active['duration_seconds'] ?? $journey['rest_duration_seconds'] ?? 0);
            if (is_string($started) && $duration > 0) {
                try {
                    $elapsed = now()->getTimestamp() - (new \DateTimeImmutable($started))->getTimestamp();
                    $remainingRestSeconds = max(0, $duration - $elapsed);
                } catch (\Throwable) {
                    $remainingRestSeconds = null;
                }
            }
        }

        return [
            'active' => $isResting,
            'message' => $isResting ? 'Vehicle is resting at a planned stop' : 'No active rest stop',
            'rest_stop_count' => count($stops),
            'active_rest_stop' => $active,
            'next_rest_stop' => $isResting ? $active : $next,
            'distance_to_next_rest_m' => $distanceToNext,
            'remaining_rest_seconds' => $remainingRestSeconds,
            'rest_started_at' => $journey['rest_started_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $progress
     * @return array<string, mixed>|null
     */
    private function buildRouteBlock(?array $progress): ?array
    {
        if ($progress === null) {
            return null;
        }

        $coord = is_array($progress['current_coordinate'] ?? null)
            ? $progress['current_coordinate']
            : [
                'latitude' => $progress['current_latitude'] ?? null,
                'longitude' => $progress['current_longitude'] ?? null,
            ];

        return [
            'completion_percentage' => isset($progress['completion_percentage'])
                ? (float) $progress['completion_percentage']
                : null,
            'distance_travelled_m' => isset($progress['distance_travelled_m'])
                ? (float) $progress['distance_travelled_m']
                : null,
            'remaining_distance_m' => isset($progress['remaining_distance_m'])
                ? (float) $progress['remaining_distance_m']
                : null,
            'total_distance_m' => isset($progress['total_distance_m'])
                ? (float) $progress['total_distance_m']
                : null,
            'completed' => (bool) ($progress['completed'] ?? false),
            'heading' => isset($progress['heading']) ? (float) $progress['heading'] : null,
            'last_tick' => $progress['last_tick'] ?? null,
            'current_coordinate' => [
                'latitude' => isset($coord['latitude']) ? (float) $coord['latitude'] : null,
                'longitude' => isset($coord['longitude']) ? (float) $coord['longitude'] : null,
            ],
        ];
    }

    /**
     * @param Shipment $shipment
     * @param array<string, mixed>|null $journey
     * @param array<string, mixed>|null $progress
     * @return array<string, mixed>|null
     */
    private function buildEtaBlock(Shipment $shipment, ?array $journey, ?array $progress): ?array
    {
        $etaService = new \App\Services\Eta\FLeetXEnhancedETAService();
        return $etaService->calculateEta($shipment, $journey, $progress);
    }

    private function enrollmentLabel(?Assignment $assignment, mixed $status): string
    {
        if ($assignment === null) {
            return 'Waiting for Assignment — Engine simulation unavailable';
        }

        if ($status === null || $status === '') {
            return 'Not enrolled';
        }

        return match ((string) $status) {
            EngineEnrollmentStatus::ENROLLED => 'Engine enrolled',
            EngineEnrollmentStatus::ACTIVE => 'Engine active',
            EngineEnrollmentStatus::COMPLETED => 'Engine enrollment completed',
            EngineEnrollmentStatus::CANCELLED => 'Engine enrollment cancelled',
            default => 'Engine enrollment: '.(string) $status,
        };
    }
}
