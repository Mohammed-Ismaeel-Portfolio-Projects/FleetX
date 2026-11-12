<?php

namespace App\Simulation\Modules;

use App\Actions\Shipment\GetShipmentRouteAction;
use App\Models\Assignment;
use App\Simulation\Enrollment\SimulationEligibleAssignments;
use App\Models\TrackingLog;
use App\Services\TrackingSimulator\EuropeanFleetLocations;
use App\Services\TrackingSimulator\VehicleMovementService;
use App\Simulation\Contracts\EngineContextInterface;
use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Dynamics\DynamicsTickResult;
use App\Simulation\Journey\JourneyRestEngine;
use App\Simulation\Progress\RouteProgressEngine;
use App\Simulation\Progress\RouteProgressState;
use App\Simulation\Routing\Application\Services\PlannedRouteCache;
use Illuminate\Support\Facades\Log;

/**
 * RouteProgressModule
 *
 * Ensures an immutable planned route exists (first request only), then advances
 * along cached geometry via RouteProgressEngine. Does not regenerate routes.
 */
class RouteProgressModule implements SimulationModuleInterface
{
    public const CONTEXT_KEY = 'route_progress_runtime';

    private EngineContextInterface $context;

    /** @var array<string, true> */
    private array $arrivalCache = [];

    public function __construct(
        private RouteProgressEngine $routeProgressEngine,
        private EuropeanFleetLocations $fleetLocations,
        private VehicleMovementService $movementService,
        private ?JourneyRestEngine $journeyRestEngine = null,
    ) {}

    private function journey(): JourneyRestEngine
    {
        return $this->journeyRestEngine ??= JourneyRestEngine::fromContainer();
    }

    public function initialize(EngineContextInterface $context): void
    {
        $this->context = $context;
    }

    public function start(): void
    {
        $this->arrivalCache = [];
    }

    public function tick(): void
    {
        $states = $this->context->getRegistry(VehicleStateMachineModule::CONTEXT_KEY, []);
        $dynamics = $this->context->getRegistry(VehicleDynamicsModule::CONTEXT_KEY, []);
        if (!is_array($states)) {
            $states = [];
        }
        if (!is_array($dynamics)) {
            $dynamics = [];
        }

        $runtime = [];

        $assignments = SimulationEligibleAssignments::query()
            ->with(['shipment', 'vehicle'])
            ->get();

        foreach ($assignments as $assignment) {
            if (!$assignment->vehicle || !$assignment->shipment) {
                continue;
            }

            $vehicleId = (string) $assignment->vehicle_id;
            $operational = $states[$vehicleId]
                ?? $states['by_assignment'][$assignment->id]
                ?? 'offline';

            if (!is_string($operational)) {
                $operational = 'offline';
            }

            $tickDynamics = $this->resolveDynamics($dynamics, $vehicleId);
            $this->ensureImmutablePlannedRoute((string) $assignment->shipment_id);
            $entry = $this->resolvePosition($assignment, $operational, $tickDynamics);
            $runtime[$vehicleId] = $entry;
        }

        $this->context->setRegistry(self::CONTEXT_KEY, $runtime);
    }

    /**
     * First-request bootstrap only. GetShipmentRouteAction returns frozen cache when present.
     * Resolved via container so SimulationRuntime module wiring stays unchanged.
     */
    private function ensureImmutablePlannedRoute(string $shipmentId): void
    {
        $plannedRouteCache = app(PlannedRouteCache::class);
        if ($plannedRouteCache->has($shipmentId)) {
            return;
        }

        $result = app(GetShipmentRouteAction::class)->execute($shipmentId);
        if (!$result->success) {
            Log::warning('RouteProgressModule: failed to bootstrap immutable planned route', [
                'shipment_id' => $shipmentId,
                'message' => $result->message,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $dynamics
     */
    private function resolveDynamics(array $dynamics, string $vehicleId): ?DynamicsTickResult
    {
        $cached = $dynamics[$vehicleId] ?? null;
        if ($cached instanceof DynamicsTickResult) {
            return $cached;
        }
        if (is_array($cached) && ($cached['_result'] ?? null) instanceof DynamicsTickResult) {
            return $cached['_result'];
        }

        return null;
    }

    /**
     * @return array{
     *     progress: ?RouteProgressState,
     *     tracking_runtime: ?array,
     *     operational_state: string
     * }
     */
    private function resolvePosition(
        Assignment $assignment,
        string $operational,
        ?DynamicsTickResult $dynamics,
    ): array {
        $vehicleId = (string) $assignment->vehicle_id;

        if ($operational === 'offline') {
            return [
                'progress' => $this->routeProgressEngine->getProgress((string) $assignment->shipment_id),
                'tracking_runtime' => null,
                'operational_state' => $operational,
            ];
        }

        if ($operational === 'moving') {
            $speed = $dynamics?->effectiveSpeedKmhForDistance() ?? 0.0;
            $tickSeconds = (int) max(1, round($dynamics?->tickSeconds ?? 60.0));
            $shipmentId = (string) $assignment->shipment_id;

            $existing = $this->routeProgressEngine->getProgress($shipmentId);
            $previousTravelled = $existing?->distanceTravelledM;
            $totalHint = $existing?->totalDistanceM;

            if ($this->journey()->isResting($shipmentId)) {
                $held = $existing;
                $this->journey()->syncWithProgress(
                    $shipmentId,
                    $held?->distanceTravelledM ?? 0.0,
                    $previousTravelled,
                    now()->toIso8601String(),
                    $held?->totalDistanceM ?? ($totalHint ?? 0.0),
                );

                return [
                    'progress' => $held,
                    'tracking_runtime' => null,
                    'operational_state' => 'idle',
                    'journey_resting' => true,
                ];
            }

            if ($totalHint !== null && $totalHint > 0) {
                $this->journey()->ensurePlan($shipmentId, $totalHint);
            }

            $cap = $existing !== null
                ? $this->journey()->maxTravelledCapM($shipmentId, $existing->distanceTravelledM)
                : null;

            $progress = $this->routeProgressEngine->advance(
                $assignment,
                $speed,
                $tickSeconds,
                $cap,
            );

            if ($progress !== null) {
                $this->journey()->ensurePlan($shipmentId, $progress->totalDistanceM);
                $this->journey()->syncWithProgress(
                    $shipmentId,
                    $progress->distanceTravelledM,
                    $previousTravelled,
                    now()->toIso8601String(),
                    $progress->totalDistanceM,
                );

                return [
                    'progress' => $progress,
                    'tracking_runtime' => null,
                    'operational_state' => $this->journey()->isResting($shipmentId) ? 'idle' : $operational,
                    'journey_resting' => $this->journey()->isResting($shipmentId),
                ];
            }

            Log::warning('RouteProgressEngine unavailable; using land-corridor fallback', [
                'shipment_id' => $assignment->shipment_id,
                'vehicle_id' => $assignment->vehicle_id,
            ]);

            return [
                'progress' => null,
                'tracking_runtime' => $this->fallbackCorridorMove($assignment, $speed),
                'operational_state' => $operational,
            ];
        }

        // idle / stopped — hold current progress position; still tick rest timers
        $progress = $this->routeProgressEngine->getProgress((string) $assignment->shipment_id);
        if ($progress !== null) {
            $this->journey()->syncWithProgress(
                (string) $assignment->shipment_id,
                $progress->distanceTravelledM,
                $progress->distanceTravelledM,
                now()->toIso8601String(),
                $progress->totalDistanceM,
            );

            return [
                'progress' => $progress,
                'tracking_runtime' => null,
                'operational_state' => $operational,
                'journey_resting' => $this->journey()->isResting((string) $assignment->shipment_id),
            ];
        }

        $last = $this->lastKnownOrOrigin($assignment);
        $destination = $this->destinationCoordinates($assignment);

        return [
            'progress' => null,
            'tracking_runtime' => [
                'latitude' => $last['latitude'],
                'longitude' => $last['longitude'],
                'heading' => $this->movementService->calculateBearing(
                    $last['latitude'],
                    $last['longitude'],
                    $destination['latitude'],
                    $destination['longitude']
                ),
                'from_fallback' => true,
                'arrived' => false,
            ],
            'operational_state' => $operational,
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, heading: float, from_fallback: bool, arrived: bool}
     */
    private function fallbackCorridorMove(Assignment $assignment, float $speedKmh): array
    {
        $destination = $this->destinationCoordinates($assignment);
        $path = $this->fleetLocations->buildLandPath(
            (string) ($assignment->shipment->pickup_city ?? 'Berlin'),
            (string) ($assignment->shipment->delivery_city ?? 'Paris'),
        );
        $last = $this->lastKnownOrOrigin($assignment);

        $movement = $this->fleetLocations->advanceAlongLandPath(
            $path,
            $last['latitude'],
            $last['longitude'],
            $speedKmh,
            60,
            0,
            100
        );

        if ($movement['arrived'] || isset($this->arrivalCache[(string) $assignment->id])) {
            $this->arrivalCache[(string) $assignment->id] = true;

            return [
                'latitude' => $destination['latitude'],
                'longitude' => $destination['longitude'],
                'heading' => $movement['heading'],
                'from_fallback' => true,
                'arrived' => true,
            ];
        }

        return [
            'latitude' => $movement['latitude'],
            'longitude' => $movement['longitude'],
            'heading' => $movement['heading'],
            'from_fallback' => true,
            'arrived' => false,
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function lastKnownOrOrigin(Assignment $assignment): array
    {
        $lastLog = TrackingLog::where('shipment_id', $assignment->shipment_id)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if ($lastLog) {
            return [
                'latitude' => (float) $lastLog->latitude,
                'longitude' => (float) $lastLog->longitude,
            ];
        }

        $pickupCity = $assignment->shipment->pickup_city ?? 'Berlin';

        return $this->movementService->generateRandomCoordinates($pickupCity);
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    private function destinationCoordinates(Assignment $assignment): array
    {
        $deliveryCity = $assignment->shipment->delivery_city ?? 'Paris';

        return $this->movementService->generateRandomCoordinates($deliveryCity);
    }

    public function pause(): void {}

    public function resume(): void {}

    public function stop(): void {}

    public function shutdown(): void {}

    public function getName(): string
    {
        return 'route_progress';
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getDependencies(): array
    {
        return ['vehicle_state_machine', 'vehicle_dynamics'];
    }

    public function getExecutionInterval(): int
    {
        return 0;
    }
}
