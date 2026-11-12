<?php

namespace App\Simulation\Routing\Application\Services;

use App\Simulation\Routing\Contracts\RoutingProviderInterface;
use App\Simulation\Routing\Contracts\ProviderResponseMapperInterface;

/**
 * ResponseMapperResolver Service.
 * Resolves the appropriate response mapper for a provider.
 */
class ResponseMapperResolver
{
    /**
     * Registered mappers keyed by provider name.
     * @var array<string, ProviderResponseMapperInterface>
     */
    private array $mappers = [];

    /**
     * Register a response mapper.
     *
     * @param string $providerName The provider name
     * @param ProviderResponseMapperInterface $mapper The response mapper
     */
    public function registerMapper(string $providerName, ProviderResponseMapperInterface $mapper): void
    {
        $this->mappers[$providerName] = $mapper;
    }

    /**
     * Resolve response mapper for provider.
     *
     * @param RoutingProviderInterface $provider The provider
     * @return ProviderResponseMapperInterface|null The mapper, or null if not found
     */
    public function resolve(RoutingProviderInterface $provider): ?ProviderResponseMapperInterface
    {
        $providerName = $provider->getProviderName();
        return $this->mappers[$providerName] ?? null;
    }

    /**
     * Resolve response mapper by provider name.
     *
     * @param string $providerName The provider name
     * @return ProviderResponseMapperInterface|null The mapper, or null if not found
     */
    public function resolveByName(string $providerName): ?ProviderResponseMapperInterface
    {
        return $this->mappers[$providerName] ?? null;
    }

    /**
     * Get all registered mappers.
     *
     * @return ProviderResponseMapperInterface[]
     */
    public function getAllMappers(): array
    {
        return $this->mappers;
    }

    /**
     * Check if mapper is registered for provider.
     *
     * @param string $providerName The provider name
     * @return bool True if registered, false otherwise
     */
    public function hasMapper(string $providerName): bool
    {
        return isset($this->mappers[$providerName]);
    }
}
