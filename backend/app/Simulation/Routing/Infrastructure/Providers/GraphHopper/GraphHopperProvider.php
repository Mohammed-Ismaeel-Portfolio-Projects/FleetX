<?php

namespace App\Simulation\Routing\Infrastructure\Providers\GraphHopper;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Enums\ProviderStatus;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use App\Simulation\Routing\ValueObjects\Coordinate;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GraphHopperProvider
 *
 * GraphHopper Directions API routing provider.
 * Communicates with GraphHopper HTTP API and returns raw JSON.
 *
 * Responsibilities:
 * - Build GraphHopper HTTP request from RoutePlanningRequest
 * - Adapt classic (Free) vs flexible (Paid) request bodies via config
 * - Apply truck profile + dimension restrictions when Flexible Routing is enabled
 * - Gracefully downgrade unsupported truck/small_truck profiles to car
 * - Call GraphHopper API via Laravel HTTP Client
 * - Validate HTTP response
 * - Return raw GraphHopper JSON
 *
 * Does NOT:
 * - Decode polyline
 * - Map response to domain objects
 * - Create Route aggregates
 */
class GraphHopperProvider implements RoutingProviderInterface
{
    private const TRUCK_PROFILES = ['truck', 'small_truck'];

    private ProviderStatus $status = ProviderStatus::AVAILABLE;
    private ?string $lastError = null;

    /**
     * Remember when this API key/account rejected truck profiles (process lifetime).
     * Cleared automatically if a truck profile later succeeds.
     */
    private static bool $truckProfilesUnsupported = false;

    private static bool $loggedProfileDowngrade = false;

    private static bool $loggedRoutingMode = false;

    /**
     * Calculate route using GraphHopper Directions API.
     *
     * @return array Raw GraphHopper JSON response
     * @throws RoutingProviderUnavailableException
     */
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        if (!$this->isAvailable()) {
            throw RoutingProviderUnavailableException::fromProvider($this->getProviderName());
        }

        $vehicleProfile = $request->context->vehicle;
        $payload = $this->buildRequestPayload($request->origin, $request->destination, $vehicleProfile);
        $payload = $this->applyKnownCompatibleProfile($payload);

        try {
            $response = $this->postRoute($payload);

            if ($this->isUnsupportedProfileResponse($response, $payload['profile'] ?? null)) {
                $payload = $this->downgradePayloadToCarProfile($payload, (string) ($payload['profile'] ?? 'truck'));
                $response = $this->postRoute($payload);
            }

            return $this->parseSuccessfulResponse($response);

        } catch (RoutingProviderUnavailableException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->lastError = "Connection failed: {$e->getMessage()}";
            Log::error('GraphHopper connection error', ['error' => $e->getMessage()]);
            throw new RoutingProviderUnavailableException(
                "Failed to connect to GraphHopper: {$e->getMessage()}",
                0,
                $e,
                $this->getProviderName()
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $this->lastError = "Request failed: {$e->getMessage()}";
            Log::error('GraphHopper request error', ['error' => $e->getMessage()]);
            throw new RoutingProviderUnavailableException(
                "GraphHopper request failed: {$e->getMessage()}",
                0,
                $e,
                $this->getProviderName()
            );
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $isTimeout = str_contains(strtolower($message), 'timed out')
                || str_contains(strtolower($message), 'timeout')
                || $e instanceof \Illuminate\Http\Client\ConnectionException;

            $this->lastError = $isTimeout ? 'Request timeout' : "Unexpected error: {$message}";
            Log::error('GraphHopper unexpected error', ['error' => $message]);

            throw new RoutingProviderUnavailableException(
                $isTimeout
                    ? 'GraphHopper request timed out'
                    : "Unexpected error calling GraphHopper: {$message}",
                0,
                $e,
                $this->getProviderName()
            );
        }
    }

    /**
     * Whether Flexible Routing features are enabled (custom_model / ch.disable / truck profiles).
     */
    public function isFlexibleRoutingEnabled(): bool
    {
        return (bool) config('routing.graphhopper.enable_flexible_routing', false);
    }

    /**
     * Build GraphHopper POST /route JSON body.
     *
     * Classic mode (Free plan): profile + points only — no custom_model / ch.disable.
     * Flexible mode (Paid): truck-aware profile + optional dimension custom_model.
     *
     * Points use GraphHopper POST order: [longitude, latitude].
     * Intermediate land-corridor waypoints are ignored (Simulator fallback only).
     */
    public function buildRequestPayload(
        Coordinate $origin,
        Coordinate $destination,
        VehicleProfile $vehicleProfile,
    ): array {
        $this->logRoutingModeOnce();

        $points = [
            [$origin->longitude, $origin->latitude],
            [$destination->longitude, $destination->latitude],
        ];

        if (!$this->isFlexibleRoutingEnabled()) {
            return $this->buildClassicRequestPayload($points);
        }

        return $this->buildFlexibleRequestPayload($points, $vehicleProfile);
    }

    /**
     * Free-plan compatible request body (no Flexible Routing fields).
     *
     * @param list<array{0: float, 1: float}> $points
     * @return array{points: list<array{0: float, 1: float}>, profile: string, points_encoded: bool, instructions: bool, calc_points: bool}
     */
    private function buildClassicRequestPayload(array $points): array
    {
        $configured = (string) config('routing.graphhopper.profile', 'car');

        return [
            'points' => $points,
            'profile' => $configured !== '' ? $configured : 'car',
            'points_encoded' => true,
            'instructions' => true,
            'calc_points' => true,
        ];
    }

    /**
     * Paid Flexible Routing body: truck/small_truck + optional custom_model.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    private function buildFlexibleRequestPayload(array $points, VehicleProfile $vehicleProfile): array
    {
        $profile = $this->resolveGraphHopperProfile($vehicleProfile);
        $enableRestrictions = (bool) config('routing.graphhopper.enable_truck_restrictions', true);

        $payload = [
            'points' => $points,
            'profile' => $profile,
            'points_encoded' => true,
            'instructions' => true,
            'calc_points' => true,
        ];

        if ($enableRestrictions && $vehicleProfile->isLargeVehicle()) {
            $customModel = $this->buildTruckCustomModel($vehicleProfile);
            if ($customModel !== null) {
                $payload['ch.disable'] = true;
                $payload['custom_model'] = $customModel;
            }
        }

        return $payload;
    }

    private function logRoutingModeOnce(): void
    {
        if (self::$loggedRoutingMode) {
            return;
        }

        self::$loggedRoutingMode = true;

        if ($this->isFlexibleRoutingEnabled()) {
            Log::info('Using Flexible GraphHopper Mode');
        } else {
            Log::info('Using Classic GraphHopper Mode');
        }
    }

    /**
     * Map FleetX freight class / config to a GraphHopper routing profile.
     * Used by Flexible Routing mode (truck-aware). Classic mode uses GRAPH_HOPPER_PROFILE.
     */
    public function resolveGraphHopperProfile(VehicleProfile $vehicleProfile): string
    {
        $configured = (string) config('routing.graphhopper.profile', 'truck');
        $freightClass = $vehicleProfile->freightClass
            ?? (string) config('routing.graphhopper.vehicle_type', 'heavy_truck');

        // Prefer explicit freight-class mapping for truck-aware routing.
        return match ($freightClass) {
            VehicleProfile::FREIGHT_VAN,
            VehicleProfile::FREIGHT_LIGHT_TRUCK => 'small_truck',
            VehicleProfile::FREIGHT_HEAVY_TRUCK => 'truck',
            default => $configured !== '' ? $configured : 'truck',
        };
    }

    /**
     * Build GraphHopper custom_model that excludes roads too small for this truck.
     *
     * @return array{priority: list<array{if: string, multiply_by: string}>}|null
     */
    public function buildTruckCustomModel(VehicleProfile $vehicleProfile): ?array
    {
        $conditions = [];

        if ($vehicleProfile->height !== null && $vehicleProfile->height > 0) {
            $height = number_format($vehicleProfile->height, 2, '.', '');
            $conditions[] = "max_height < {$height}";
        }

        if ($vehicleProfile->width !== null && $vehicleProfile->width > 0) {
            $width = number_format($vehicleProfile->width, 2, '.', '');
            $conditions[] = "max_width < {$width}";
        }

        if ($vehicleProfile->length !== null && $vehicleProfile->length > 0) {
            $length = number_format($vehicleProfile->length, 2, '.', '');
            $conditions[] = "max_length < {$length}";
        }

        if ($vehicleProfile->weight !== null && $vehicleProfile->weight > 0) {
            $weightTons = number_format($vehicleProfile->weight / 1000, 3, '.', '');
            $conditions[] = "max_weight < {$weightTons}";
        }

        if ($conditions === []) {
            return null;
        }

        return [
            'priority' => [
                [
                    'if' => implode(' || ', $conditions),
                    'multiply_by' => '0',
                ],
            ],
        ];
    }

    /**
     * @internal Reset process-lifetime profile cache (tests).
     */
    public static function resetProfileCompatibilityCache(): void
    {
        self::$truckProfilesUnsupported = false;
        self::$loggedProfileDowngrade = false;
        self::$loggedRoutingMode = false;
    }

    private function applyKnownCompatibleProfile(array $payload): array
    {
        $profile = (string) ($payload['profile'] ?? '');

        if (self::$truckProfilesUnsupported && in_array($profile, self::TRUCK_PROFILES, true)) {
            $payload['profile'] = 'car';
        }

        return $payload;
    }

    private function downgradePayloadToCarProfile(array $payload, string $rejectedProfile): array
    {
        self::$truckProfilesUnsupported = true;

        if (!self::$loggedProfileDowngrade) {
            Log::warning('GraphHopper truck profile unsupported; downgrading to car', [
                'rejected_profile' => $rejectedProfile,
                'fallback_profile' => 'car',
                'note' => 'VehicleProfile preserved; truck profiles will be retried when supported by the account',
            ]);
            self::$loggedProfileDowngrade = true;
        }

        $payload['profile'] = 'car';

        return $payload;
    }

    /**
     * Detect GraphHopper "unsupported profile" account limitations.
     *
     * Example message:
     * "For your account the profile parameter can only be one of [car, bike, foot] but was truck"
     */
    public function isUnsupportedProfileResponse(Response $response, ?string $requestedProfile = null): bool
    {
        if ($response->status() !== 400) {
            return false;
        }

        $message = strtolower((string) ($response->json('message') ?? $response->body()));
        if ($message === '') {
            return false;
        }

        $mentionsProfileLimit = str_contains($message, 'profile')
            && (
                str_contains($message, 'can only be')
                || str_contains($message, 'not supported')
                || str_contains($message, 'unsupported')
                || str_contains($message, 'invalid profile')
            );

        if (!$mentionsProfileLimit) {
            return false;
        }

        $requested = strtolower((string) $requestedProfile);
        if ($requested !== '' && in_array($requested, self::TRUCK_PROFILES, true)) {
            return true;
        }

        return str_contains($message, 'truck') || str_contains($message, 'small_truck');
    }

    private function postRoute(array $payload): Response
    {
        return Http::timeout((int) config('routing.graphhopper.timeout', 30))
            ->acceptJson()
            ->withQueryParameters([
                'key' => (string) config('routing.graphhopper.api_key', ''),
            ])
            ->post($this->routeEndpoint(), $payload);
    }

    /**
     * @return array<string, mixed>
     * @throws RoutingProviderUnavailableException
     */
    private function parseSuccessfulResponse(Response $response): array
    {
        if (!$response->successful()) {
            $this->lastError = "HTTP error: {$response->status()}";
            if (in_array($response->status(), [401, 403], true)) {
                $this->status = ProviderStatus::UNAVAILABLE;
            }
            throw new RoutingProviderUnavailableException(
                "GraphHopper API returned HTTP {$response->status()}: {$response->body()}",
                $response->status(),
                previous: null,
                provider: $this->getProviderName()
            );
        }

        $data = $response->json();
        if ($data === null || !is_array($data)) {
            $this->lastError = 'Invalid JSON response';
            throw new RoutingProviderUnavailableException(
                'GraphHopper API returned invalid JSON',
                0,
                previous: null,
                provider: $this->getProviderName()
            );
        }

        if (isset($data['message']) && !isset($data['paths'])) {
            $this->lastError = 'GraphHopper error: ' . $data['message'];
            throw new RoutingProviderUnavailableException(
                'GraphHopper API error: ' . $data['message'],
                0,
                previous: null,
                provider: $this->getProviderName()
            );
        }

        if (!isset($data['paths']) || !is_array($data['paths']) || empty($data['paths'])) {
            $this->lastError = 'GraphHopper response missing paths';
            throw new RoutingProviderUnavailableException(
                'GraphHopper API returned no route paths',
                0,
                previous: null,
                provider: $this->getProviderName()
            );
        }

        $this->status = ProviderStatus::AVAILABLE;
        $this->lastError = null;

        return $data;
    }

    private function routeEndpoint(): string
    {
        $baseUrl = rtrim((string) config('routing.graphhopper.url', 'https://graphhopper.com/api/1'), '/');

        return $baseUrl . '/route';
    }

    public function getProviderName(): string
    {
        return 'graphhopper';
    }

    public function isAvailable(): bool
    {
        $apiKey = trim((string) config('routing.graphhopper.api_key', ''));

        return $apiKey !== '' && $this->status === ProviderStatus::AVAILABLE;
    }

    public function getStatus(): ProviderStatus
    {
        return $this->status;
    }

    public function setStatus(ProviderStatus $status): void
    {
        $this->status = $status;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getProviderVersion(): string
    {
        return 'v1';
    }
}
