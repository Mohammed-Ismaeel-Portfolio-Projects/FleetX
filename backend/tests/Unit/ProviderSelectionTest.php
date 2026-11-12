<?php

namespace Tests\Unit;

use App\Simulation\Routing\Application\DTOs\RoutePlanningOptions;
use App\Simulation\Routing\Application\Models\RoutePlanningContext;
use App\Simulation\Routing\Application\Models\VehicleProfile;
use App\Simulation\Routing\Application\Resolvers\ProviderResolver;
use App\Simulation\Routing\Application\Services\ResponseMapperResolver;
use App\Simulation\Routing\Infrastructure\Providers\OSRM\OSRMProvider;
use App\Simulation\Routing\Infrastructure\Providers\Simulator\SimulatorProvider;
use App\Simulation\Routing\Infrastructure\ResponseMappers\OSRMResponseMapper;
use App\Simulation\Routing\Infrastructure\ResponseMappers\SimulatorResponseMapper;
use App\Simulation\Routing\Enums\TravelMode;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * ProviderSelectionTest
 * 
 * Tests for provider selection and fallback behavior.
 */
class ProviderSelectionTest extends TestCase
{
    private ProviderResolver $providerResolver;
    private ResponseMapperResolver $mapperResolver;
    private VehicleProfile $vehicleProfile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->providerResolver = new ProviderResolver();
        $this->mapperResolver = new ResponseMapperResolver();
        $this->vehicleProfile = VehicleProfile::truck(4.0, 2.5, 12.0, 18000);
        
        // Register providers
        $osrmProvider = new OSRMProvider();
        $this->providerResolver->registerProvider(
            $osrmProvider,
            priority: 200,
            capabilities: [
                'supported_modes' => ['driving', 'cycling', 'walking'],
                'supported_vehicle_types' => ['car', 'truck', 'motorcycle'],
                'supports_large_vehicles' => true,
            ]
        );
        
        $simulatorProvider = new SimulatorProvider();
        $this->providerResolver->registerProvider(
            $simulatorProvider,
            priority: 100,
            capabilities: [
                'supported_modes' => ['driving', 'walking', 'cycling'],
                'supported_vehicle_types' => ['car', 'truck', 'motorcycle'],
                'supports_large_vehicles' => true,
            ]
        );
        
        // Register mappers
        $osrmMapper = new OSRMResponseMapper();
        $this->mapperResolver->registerMapper('osrm', $osrmMapper);
        
        $simulatorMapper = new SimulatorResponseMapper();
        $this->mapperResolver->registerMapper('simulator', $simulatorMapper);
    }

    /**
     * Test OSRM selected when configured as default provider.
     */
    public function test_osrm_selected_when_configured()
    {
        Config::set('routing.default_provider', 'osrm');
        Config::set('routing.fallback_enabled', false);

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertInstanceOf(OSRMProvider::class, $provider);
        $this->assertEquals('osrm', $provider->getProviderName());
        $this->assertEquals('osrm', $this->providerResolver->getLastResolvedProviderName());
    }

    /**
     * Test Simulator selected when configured as default provider.
     */
    public function test_simulator_selected_when_configured()
    {
        Config::set('routing.default_provider', 'simulator');
        Config::set('routing.fallback_enabled', false);

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertInstanceOf(SimulatorProvider::class, $provider);
        $this->assertEquals('simulator', $provider->getProviderName());
        $this->assertEquals('simulator', $this->providerResolver->getLastResolvedProviderName());
    }

    /**
     * Test fallback to Simulator when OSRM unavailable and fallback enabled.
     */
    public function test_fallback_to_simulator_when_osrm_unavailable()
    {
        Config::set('routing.default_provider', 'osrm');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');

        // Make OSRM unavailable
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        if ($osrmProvider) {
            $osrmProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertInstanceOf(SimulatorProvider::class, $provider);
        $this->assertEquals('simulator', $provider->getProviderName());
        $this->assertEquals('simulator', $this->providerResolver->getLastResolvedProviderName());
    }

    /**
     * Test no fallback when fallback disabled.
     */
    public function test_no_fallback_when_disabled()
    {
        Config::set('routing.default_provider', 'osrm');
        Config::set('routing.fallback_enabled', false);

        // Make OSRM unavailable
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        if ($osrmProvider) {
            $osrmProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertNull($provider);
    }

    /**
     * Test priority-based selection when no default provider configured.
     */
    public function test_priority_based_selection_when_no_default_configured()
    {
        Config::set('routing.default_provider', null);

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        // OSRM has higher priority (200) than Simulator (100)
        $this->assertInstanceOf(OSRMProvider::class, $provider);
        $this->assertEquals('osrm', $provider->getProviderName());
    }

    /**
     * Test ResponseMapperResolver selects OSRM mapper.
     */
    public function test_response_mapper_resolver_selects_osrm_mapper()
    {
        $osrmProvider = new OSRMProvider();
        $mapper = $this->mapperResolver->resolve($osrmProvider);

        $this->assertInstanceOf(OSRMResponseMapper::class, $mapper);
        $this->assertEquals('osrm', $mapper->getSupportedProvider());
    }

    /**
     * Test ResponseMapperResolver selects Simulator mapper.
     */
    public function test_response_mapper_resolver_selects_simulator_mapper()
    {
        $simulatorProvider = new SimulatorProvider();
        $mapper = $this->mapperResolver->resolve($simulatorProvider);

        $this->assertInstanceOf(SimulatorResponseMapper::class, $mapper);
        $this->assertEquals('simulator', $mapper->getSupportedProvider());
    }

    /**
     * Test ResponseMapperResolver selects mapper by name.
     */
    public function test_response_mapper_resolver_selects_by_name()
    {
        $osrmMapper = $this->mapperResolver->resolveByName('osrm');
        $this->assertInstanceOf(OSRMResponseMapper::class, $osrmMapper);

        $simulatorMapper = $this->mapperResolver->resolveByName('simulator');
        $this->assertInstanceOf(SimulatorResponseMapper::class, $simulatorMapper);
    }

    /**
     * Test ResponseMapperResolver returns null for unknown provider.
     */
    public function test_response_mapper_resolver_returns_null_for_unknown_provider()
    {
        $unknownProvider = new class implements \App\Simulation\Routing\Contracts\RoutingProviderInterface {
            public function calculateRoute(\App\Simulation\Routing\Application\DTOs\RoutePlanningRequest $request): array { return []; }
            public function getProviderName(): string { return 'unknown'; }
            public function isAvailable(): bool { return true; }
            public function getProviderVersion(): string { return 'v1.0'; }
        };

        $mapper = $this->mapperResolver->resolve($unknownProvider);
        $this->assertNull($mapper);
    }

    /**
     * Test provider capability filtering.
     */
    public function test_provider_capability_filtering()
    {
        Config::set('routing.default_provider', null);

        // Test with unsupported travel mode
        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::WALKING);
        
        // Both providers support walking, so should return highest priority
        $this->assertNotNull($provider);
    }

    /**
     * Test resolveByName returns correct provider.
     */
    public function test_resolve_by_name_returns_correct_provider()
    {
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        $this->assertInstanceOf(OSRMProvider::class, $osrmProvider);
        $this->assertEquals('osrm', $osrmProvider->getProviderName());

        $simulatorProvider = $this->providerResolver->resolveByName('simulator');
        $this->assertInstanceOf(SimulatorProvider::class, $simulatorProvider);
        $this->assertEquals('simulator', $simulatorProvider->getProviderName());
    }

    /**
     * Test resolveByName returns null for unknown provider.
     */
    public function test_resolve_by_name_returns_null_for_unknown_provider()
    {
        $provider = $this->providerResolver->resolveByName('unknown');
        $this->assertNull($provider);
    }

    /**
     * Test getAllProviders returns all registered providers.
     */
    public function test_get_all_providers()
    {
        $providers = $this->providerResolver->getAllProviders();

        $this->assertCount(2, $providers);
        $this->assertArrayHasKey('osrm', $providers);
        $this->assertArrayHasKey('simulator', $providers);
        $this->assertInstanceOf(OSRMProvider::class, $providers['osrm']);
        $this->assertInstanceOf(SimulatorProvider::class, $providers['simulator']);
    }

    /**
     * Test getAvailableProviders filters unavailable providers.
     */
    public function test_get_available_providers_filters_unavailable()
    {
        // Make OSRM unavailable
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        if ($osrmProvider) {
            $osrmProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }

        $availableProviders = $this->providerResolver->getAvailableProviders();

        $this->assertCount(1, $availableProviders);
        $this->assertArrayHasKey('simulator', $availableProviders);
        $this->assertArrayNotHasKey('osrm', $availableProviders);
    }

    /**
     * Test fallback to custom fallback provider.
     */
    public function test_fallback_to_custom_provider()
    {
        Config::set('routing.default_provider', 'osrm');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');

        // Make OSRM unavailable
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        if ($osrmProvider) {
            $osrmProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertInstanceOf(SimulatorProvider::class, $provider);
    }

    /**
     * Test fallback when both primary and fallback are unavailable.
     */
    public function test_returns_null_when_both_unavailable()
    {
        Config::set('routing.default_provider', 'osrm');
        Config::set('routing.fallback_enabled', true);
        Config::set('routing.fallback_provider', 'simulator');

        // Make both unavailable
        $osrmProvider = $this->providerResolver->resolveByName('osrm');
        $simulatorProvider = $this->providerResolver->resolveByName('simulator');
        
        if ($osrmProvider) {
            $osrmProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }
        if ($simulatorProvider) {
            $simulatorProvider->setStatus(\App\Simulation\Routing\Enums\ProviderStatus::UNAVAILABLE);
        }

        $provider = $this->providerResolver->resolve($this->vehicleProfile, TravelMode::DRIVING);

        $this->assertNull($provider);
    }
}
