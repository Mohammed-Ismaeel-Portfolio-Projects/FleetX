<?php

namespace App\Simulation\Routing\Application\Resolvers;

use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Enums\TravelMode;
use App\Simulation\Routing\Contracts\RoutingProviderInterface;

/**
 * ProviderResolver.
 * Resolves the appropriate routing provider based on vehicle profile, travel mode, and configuration.
 * Evolved into a routing strategy resolver for enterprise-scale provider selection.
 */
class ProviderResolver
{
    /**
     * Registered providers keyed by name.
     * @var RoutingProviderInterface[]
     */
    private array $providers = [];

    /**
     * Provider priority configuration.
     * @var array<string, int>
     */
    private array $providerPriorities = [];

    /**
     * Provider capabilities.
     * @var array<string, array>
     */
    private array $providerCapabilities = [];

    /**
     * Last resolved provider name.
     * @var string|null
     */
    private ?string $lastResolvedProviderName = null;

    /**
     * Register a routing provider.
     *
     * @param RoutingProviderInterface $provider The provider to register
     * @param int $priority Provider priority (higher = preferred)
     * @param array $capabilities Provider capabilities (supported modes, vehicle types, etc.)
     */
    public function registerProvider(
        RoutingProviderInterface $provider,
        int $priority = 0,
        array $capabilities = [],
    ): void {
        $providerName = $provider->getProviderName();
        $this->providers[$providerName] = $provider;
        $this->providerPriorities[$providerName] = $priority;
        $this->providerCapabilities[$providerName] = $capabilities;
    }

    /**
     * Resolve the best provider for the given context.
     * Uses configuration-based selection if configured, otherwise falls back to priority-based.
     *
     * @param VehicleProfile $vehicleProfile The vehicle profile
     * @param TravelMode $travelMode The travel mode
     * @return RoutingProviderInterface|null The resolved provider, or null if none available
     */
    public function resolve(
        VehicleProfile $vehicleProfile,
        TravelMode $travelMode,
    ): ?RoutingProviderInterface {
        // Check if a specific provider is configured
        $defaultProvider = config('routing.default_provider');
        
        if ($defaultProvider) {
            $provider = $this->resolveConfiguredProvider($defaultProvider, $vehicleProfile, $travelMode);
            if ($provider !== null) {
                return $provider;
            }
            
            // If configured provider is unavailable, check fallback
            if (config('routing.fallback_enabled', false)) {
                $fallbackProvider = config('routing.fallback_provider', 'simulator');
                $provider = $this->resolveConfiguredProvider($fallbackProvider, $vehicleProfile, $travelMode);
                if ($provider !== null) {
                    return $provider;
                }
            }
            
            // If fallback not enabled or fallback also unavailable, return null
            return null;
        }
        
        // No specific provider configured, use priority-based selection
        return $this->resolveByPriority($vehicleProfile, $travelMode);
    }

    /**
     * Resolve a specific provider by name with capability checking.
     *
     * @param string $providerName The provider name
     * @param VehicleProfile $vehicleProfile The vehicle profile
     * @param TravelMode $travelMode The travel mode
     * @return RoutingProviderInterface|null The resolved provider, or null if unavailable
     */
    private function resolveConfiguredProvider(
        string $providerName,
        VehicleProfile $vehicleProfile,
        TravelMode $travelMode,
    ): ?RoutingProviderInterface {
        $provider = $this->providers[$providerName] ?? null;
        
        if (!$provider || !$provider->isAvailable()) {
            return null;
        }
        
        // Check if provider supports the requested capabilities
        if (!$this->supportsCapabilities($provider, $vehicleProfile, $travelMode)) {
            return null;
        }
        
        $this->lastResolvedProviderName = $providerName;
        return $provider;
    }

    /**
     * Resolve provider by priority (original behavior).
     *
     * @param VehicleProfile $vehicleProfile The vehicle profile
     * @param TravelMode $travelMode The travel mode
     * @return RoutingProviderInterface|null The resolved provider, or null if none available
     */
    private function resolveByPriority(
        VehicleProfile $vehicleProfile,
        TravelMode $travelMode,
    ): ?RoutingProviderInterface {
        // Filter available providers
        $availableProviders = array_filter(
            $this->providers,
            fn($provider) => $provider->isAvailable()
        );

        if (empty($availableProviders)) {
            return null;
        }

        // Filter by capabilities
        $capableProviders = $this->filterByCapabilities(
            $availableProviders,
            $vehicleProfile,
            $travelMode,
        );

        if (empty($capableProviders)) {
            // Fallback to any available provider
            $capableProviders = $availableProviders;
        }

        // Sort by priority
        uasort($capableProviders, function($a, $b) {
            $priorityA = $this->providerPriorities[$a->getProviderName()] ?? 0;
            $priorityB = $this->providerPriorities[$b->getProviderName()] ?? 0;
            return $priorityB <=> $priorityA;
        });

        // Return highest priority provider
        $provider = reset($capableProviders) ?: null;
        $this->lastResolvedProviderName = $provider?->getProviderName();
        
        return $provider;
    }

    /**
     * Resolve provider by name.
     *
     * @param string $providerName The provider name
     * @return RoutingProviderInterface|null The provider, or null if not found
     */
    public function resolveByName(string $providerName): ?RoutingProviderInterface
    {
        $this->lastResolvedProviderName = $providerName;
        return $this->providers[$providerName] ?? null;
    }

    /**
     * Get last resolved provider name.
     *
     * @return string|null The last resolved provider name
     */
    public function getLastResolvedProviderName(): ?string
    {
        return $this->lastResolvedProviderName;
    }

    /**
     * Get all registered providers.
     *
     * @return RoutingProviderInterface[]
     */
    public function getAllProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get available providers.
     *
     * @return RoutingProviderInterface[]
     */
    public function getAvailableProviders(): array
    {
        return array_filter(
            $this->providers,
            fn($provider) => $provider->isAvailable()
        );
    }

    /**
     * Filter providers by capabilities.
     *
     * @param RoutingProviderInterface[] $providers The providers to filter
     * @param VehicleProfile $vehicleProfile The vehicle profile
     * @param TravelMode $travelMode The travel mode
     * @return RoutingProviderInterface[] The filtered providers
     */
    private function filterByCapabilities(
        array $providers,
        VehicleProfile $vehicleProfile,
        TravelMode $travelMode,
    ): array {
        return array_filter($providers, function($provider) use ($vehicleProfile, $travelMode) {
            return $this->supportsCapabilities($provider, $vehicleProfile, $travelMode);
        });
    }

    /**
     * Check if a provider supports the requested capabilities.
     *
     * @param RoutingProviderInterface $provider The provider to check
     * @param VehicleProfile $vehicleProfile The vehicle profile
     * @param TravelMode $travelMode The travel mode
     * @return bool True if provider supports capabilities
     */
    private function supportsCapabilities(
        RoutingProviderInterface $provider,
        VehicleProfile $vehicleProfile,
        TravelMode $travelMode,
    ): bool {
        $providerName = $provider->getProviderName();
        $capabilities = $this->providerCapabilities[$providerName] ?? [];

        // Check travel mode support
        if (isset($capabilities['supported_modes']) 
            && !in_array($travelMode->value, $capabilities['supported_modes'])) {
            return false;
        }

        // Check vehicle type support
        if (isset($capabilities['supported_vehicle_types'])
            && !in_array($vehicleProfile->vehicleType->value, $capabilities['supported_vehicle_types'])) {
            return false;
        }

        // Check large vehicle support
        if ($vehicleProfile->isLargeVehicle()
            && ($capabilities['supports_large_vehicles'] ?? false) === false) {
            return false;
        }

        // TODO: Future: Check truck restrictions
        // TODO: Future: Check vehicle size constraints
        // TODO: Future: Check system configuration

        return true;
    }

    /**
     * Set provider capabilities.
     *
     * @param string $providerName The provider name
     * @param array $capabilities The provider capabilities
     */
    public function setProviderCapabilities(string $providerName, array $capabilities): void
    {
        $this->providerCapabilities[$providerName] = $capabilities;
    }

    /**
     * Set provider priority.
     *
     * @param string $providerName The provider name
     * @param int $priority The provider priority
     */
    public function setProviderPriority(string $providerName, int $priority): void
    {
        $this->providerPriorities[$providerName] = $priority;
    }
}
