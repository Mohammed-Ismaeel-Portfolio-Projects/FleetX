<?php

namespace App\Simulation\Routing\Infrastructure\Providers\OSRM;

use App\Simulation\Routing\Application\DTOs\RoutePlanningRequest;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Enums\ProviderStatus;
use App\Simulation\Routing\Exceptions\RoutingProviderUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OSRMProvider
 * 
 * OSRM routing provider implementation.
 * Communicates with OSRM API to calculate routes.
 * 
 * Responsibilities:
 * - Build OSRM HTTP request from RoutePlanningRequest
 * - Call OSRM API via Laravel HTTP Client
 * - Validate HTTP response
 * - Return raw OSRM JSON response
 * 
 * Does NOT:
 * - Decode polyline
 * - Calculate ETA
 * - Map response to domain objects
 * - Create Route objects
 */
class OSRMProvider implements RoutingProviderInterface
{
    private ProviderStatus $status = ProviderStatus::AVAILABLE;
    private ?string $lastError = null;

    /**
     * Calculate route using OSRM API.
     *
     * @param RoutePlanningRequest $request The route planning request
     * @return array The raw OSRM JSON response
     * @throws RoutingProviderUnavailableException
     */
    public function calculateRoute(RoutePlanningRequest $request): array
    {
        // Extract coordinates
        $origin = $request->origin;
        $destination = $request->destination;

        // Build OSRM request URL
        $url = $this->buildRequestUrl($origin, $destination);

        try {
            // Make HTTP request using Laravel HTTP Client
            $response = Http::timeout(config('routing.osrm.timeout', 30))
                ->retry(
                    config('routing.osrm.retry_attempts', 3),
                    config('routing.osrm.retry_delay', 1000)
                )
                ->get($url);

            // Validate HTTP response
            if (!$response->successful()) {
                $this->status = ProviderStatus::UNAVAILABLE;
                $this->lastError = "HTTP error: {$response->status()}";
                throw new RoutingProviderUnavailableException(
                    "OSRM API returned HTTP {$response->status()}: {$response->body()}",
                    $response->status(),
                    previous: null,
                    provider: $this->getProviderName()
                );
            }

            // Validate response body is valid JSON
            $data = $response->json();
            if ($data === null) {
                $this->status = ProviderStatus::UNAVAILABLE;
                $this->lastError = "Invalid JSON response";
                throw new RoutingProviderUnavailableException(
                    "OSRM API returned invalid JSON",
                    0,
                    previous: null,
                    provider: $this->getProviderName()
                );
            }

            // Validate OSRM response format
            if (!isset($data['code']) || $data['code'] !== 'Ok') {
                $this->status = ProviderStatus::UNAVAILABLE;
                $errorCode = $data['code'] ?? 'Unknown';
                $this->lastError = "OSRM error: {$errorCode}";
                throw new RoutingProviderUnavailableException(
                    "OSRM API returned error code: {$errorCode}",
                    0,
                    previous: null,
                    provider: $this->getProviderName()
                );
            }

            // Return raw OSRM JSON response
            return $data;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->status = ProviderStatus::UNAVAILABLE;
            $this->lastError = "Connection failed: {$e->getMessage()}";
            Log::error('OSRM connection error', ['error' => $e->getMessage()]);
            throw new RoutingProviderUnavailableException(
                "Failed to connect to OSRM server: {$e->getMessage()}",
                0,
                $e,
                $this->getProviderName()
            );

        } catch (\Illuminate\Http\Client\TimeoutException $e) {
            $this->status = ProviderStatus::UNAVAILABLE;
            $this->lastError = "Request timeout";
            Log::error('OSRM timeout error', ['error' => $e->getMessage()]);
            throw new RoutingProviderUnavailableException(
                "OSRM request timed out",
                0,
                $e,
                $this->getProviderName()
            );

        } catch (\Exception $e) {
            $this->status = ProviderStatus::UNAVAILABLE;
            $this->lastError = "Unexpected error: {$e->getMessage()}";
            Log::error('OSRM unexpected error', ['error' => $e->getMessage()]);
            throw new RoutingProviderUnavailableException(
                "Unexpected error calling OSRM: {$e->getMessage()}",
                0,
                $e,
                $this->getProviderName()
            );
        }
    }

    /**
     * Build OSRM request URL.
     *
     * @param \App\Simulation\Routing\ValueObjects\Coordinate $origin Origin coordinate
     * @param \App\Simulation\Routing\ValueObjects\Coordinate $destination Destination coordinate
     * @return string The complete OSRM API URL
     */
    private function buildRequestUrl($origin, $destination): string
    {
        $baseUrl = config('routing.osrm.url', 'http://localhost:5000');
        $profile = config('routing.osrm.profile', 'driving');

        // Format coordinates: longitude,latitude;longitude,latitude
        $coordinates = sprintf(
            '%.6f,%.6f;%.6f,%.6f',
            $origin->longitude,
            $origin->latitude,
            $destination->longitude,
            $destination->latitude
        );

        // Build query parameters
        $params = [
            'steps' => 'true',
            'overview' => 'full',
            'alternatives' => 'false',
            'geometries' => 'polyline',
            'annotations' => 'false',
        ];

        $queryString = http_build_query($params);

        return sprintf(
            '%s/route/v1/%s/%s?%s',
            rtrim($baseUrl, '/'),
            $profile,
            $coordinates,
            $queryString
        );
    }

    /**
     * Get provider name.
     *
     * @return string The provider name
     */
    public function getProviderName(): string
    {
        return 'osrm';
    }

    /**
     * Check if provider is available.
     *
     * @return bool True if available
     */
    public function isAvailable(): bool
    {
        return $this->status === ProviderStatus::AVAILABLE;
    }

    /**
     * Get provider status.
     *
     * @return ProviderStatus The provider status
     */
    public function getStatus(): ProviderStatus
    {
        return $this->status;
    }

    /**
     * Set provider status.
     *
     * @param ProviderStatus $status The status
     */
    public function setStatus(ProviderStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * Get last error.
     *
     * @return string|null The last error message
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Get provider version.
     *
     * @return string The provider version
     */
    public function getProviderVersion(): string
    {
        return 'v5.0';
    }
}
