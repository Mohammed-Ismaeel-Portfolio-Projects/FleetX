<?php

namespace App\Simulation\Routing\Infrastructure\Registry;

use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Infrastructure\Configuration\ProviderConfiguration;
use App\Simulation\Routing\Infrastructure\Exceptions\ProviderNotFoundException;
use App\Simulation\Routing\Infrastructure\Exceptions\ProviderAlreadyRegisteredException;

/**
 * ProviderRegistry.
 * Central registry for all routing providers.
 * Manages provider lifecycle and metadata.
 * 
 * Responsibilities:
 * - Register providers
 * - Resolve providers
 * - Enable providers
 * - Disable providers
 * - Get provider metadata
 * - Provider priority management
 */
class ProviderRegistry
{
    /**
     * Registered providers.
     * @var array<string, RoutingProviderInterface>
     */
    private array $providers = [];

    /**
     * Provider configurations.
     * @var array<string, ProviderConfiguration>
     */
    private array $configurations = [];

    /**
     * Provider metadata.
     * @var array<string, array>
     */
    private array $metadata = [];

    /**
     * Provider priorities.
     * @var array<string, int>
     */
    private array $priorities = [];

    /**
     * Enabled providers.
     * @var array<string, bool>
     */
    private array $enabled = [];

    /**
     * Register a provider.
     * 
     * @param RoutingProviderInterface $provider The provider to register
     * @param ProviderConfiguration $configuration The provider configuration
     * @param int $priority Provider priority (higher = preferred)
     * @param array $metadata Provider metadata
     * @throws ProviderAlreadyRegisteredException
     */
    public function register(
        RoutingProviderInterface $provider,
        ProviderConfiguration $configuration,
        int $priority = 0,
        array $metadata = [],
    ): void {
        $providerName = $provider->getProviderName();

        if (isset($this->providers[$providerName])) {
            throw new ProviderAlreadyRegisteredException("Provider '{$providerName}' is already registered");
        }

        $this->providers[$providerName] = $provider;
        $this->configurations[$providerName] = $configuration;
        $this->priorities[$providerName] = $priority;
        $this->metadata[$providerName] = $metadata;
        $this->enabled[$providerName] = true;
    }

    /**
     * Unregister a provider.
     * 
     * @param string $providerName The provider name
     * @throws ProviderNotFoundException
     */
    public function unregister(string $providerName): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        unset(
            $this->providers[$providerName],
            $this->configurations[$providerName],
            $this->priorities[$providerName],
            $this->metadata[$providerName],
            $this->enabled[$providerName]
        );
    }

    /**
     * Resolve a provider by name.
     * 
     * @param string $providerName The provider name
     * @return RoutingProviderInterface The provider
     * @throws ProviderNotFoundException
     */
    public function resolve(string $providerName): RoutingProviderInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        if (!$this->enabled[$providerName]) {
            throw new ProviderNotFoundException("Provider '{$providerName}' is disabled");
        }

        return $this->providers[$providerName];
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
     * Get enabled providers.
     * 
     * @return RoutingProviderInterface[]
     */
    public function getEnabledProviders(): array
    {
        $enabled = [];
        foreach ($this->enabled as $name => $isEnabled) {
            if ($isEnabled && isset($this->providers[$name])) {
                $enabled[$name] = $this->providers[$name];
            }
        }
        return $enabled;
    }

    /**
     * Get available providers (enabled and healthy).
     * 
     * @return RoutingProviderInterface[]
     */
    public function getAvailableProviders(): array
    {
        return array_filter(
            $this->getEnabledProviders(),
            fn($provider) => $provider->isAvailable()
        );
    }

    /**
     * Enable a provider.
     * 
     * @param string $providerName The provider name
     * @throws ProviderNotFoundException
     */
    public function enable(string $providerName): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        $this->enabled[$providerName] = true;
    }

    /**
     * Disable a provider.
     * 
     * @param string $providerName The provider name
     * @throws ProviderNotFoundException
     */
    public function disable(string $providerName): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        $this->enabled[$providerName] = false;
    }

    /**
     * Check if provider is enabled.
     * 
     * @param string $providerName The provider name
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(string $providerName): bool
    {
        return $this->enabled[$providerName] ?? false;
    }

    /**
     * Get provider configuration.
     * 
     * @param string $providerName The provider name
     * @return ProviderConfiguration The configuration
     * @throws ProviderNotFoundException
     */
    public function getConfiguration(string $providerName): ProviderConfiguration
    {
        if (!isset($this->configurations[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        return $this->configurations[$providerName];
    }

    /**
     * Set provider configuration.
     * 
     * @param string $providerName The provider name
     * @param ProviderConfiguration $configuration The configuration
     * @throws ProviderNotFoundException
     */
    public function setConfiguration(string $providerName, ProviderConfiguration $configuration): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        $this->configurations[$providerName] = $configuration;
    }

    /**
     * Get provider metadata.
     * 
     * @param string $providerName The provider name
     * @return array The metadata
     * @throws ProviderNotFoundException
     */
    public function getMetadata(string $providerName): array
    {
        if (!isset($this->metadata[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        return $this->metadata[$providerName];
    }

    /**
     * Set provider metadata.
     * 
     * @param string $providerName The provider name
     * @param array $metadata The metadata
     * @throws ProviderNotFoundException
     */
    public function setMetadata(string $providerName, array $metadata): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        $this->metadata[$providerName] = $metadata;
    }

    /**
     * Get provider priority.
     * 
     * @param string $providerName The provider name
     * @return int The priority
     * @throws ProviderNotFoundException
     */
    public function getPriority(string $providerName): int
    {
        if (!isset($this->priorities[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        return $this->priorities[$providerName];
    }

    /**
     * Set provider priority.
     * 
     * @param string $providerName The provider name
     * @param int $priority The priority
     * @throws ProviderNotFoundException
     */
    public function setPriority(string $providerName, int $priority): void
    {
        if (!isset($this->providers[$providerName])) {
            throw new ProviderNotFoundException("Provider '{$providerName}' not found");
        }

        $this->priorities[$providerName] = $priority;
    }

    /**
     * Get providers sorted by priority.
     * 
     * @return RoutingProviderInterface[] The sorted providers
     */
    public function getProvidersByPriority(): array
    {
        $providers = $this->getAvailableProviders();
        uasort($providers, function($a, $b) {
            $priorityA = $this->priorities[$a->getProviderName()] ?? 0;
            $priorityB = $this->priorities[$b->getProviderName()] ?? 0;
            return $priorityB <=> $priorityA;
        });

        return $providers;
    }

    /**
     * Check if provider is registered.
     * 
     * @param string $providerName The provider name
     * @return bool True if registered, false otherwise
     */
    public function isRegistered(string $providerName): bool
    {
        return isset($this->providers[$providerName]);
    }

    /**
     * Get provider count.
     * 
     * @return int The number of registered providers
     */
    public function getProviderCount(): int
    {
        return count($this->providers);
    }
}
