<?php

namespace Tests\Feature\Client;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientGeocodeTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $dispatcherRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->clientRole = Role::where('name', 'client')->firstOrFail();
        $this->dispatcherRole = Role::where('name', 'dispatcher')->firstOrFail();
        config()->set('routing.graphhopper.api_key', 'test-key');
        config()->set('routing.graphhopper.url', 'https://graphhopper.com/api/1');
    }

    public function test_reverse_geocode_returns_label_without_provider_name(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response([
                'hits' => [[
                    'name' => 'Beirut',
                    'city' => 'Beirut',
                    'country' => 'Lebanon',
                    'point' => ['lat' => 33.8938, 'lng' => 35.5018],
                ]],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $response = $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/reverse', [
                'latitude' => 33.8938,
                'longitude' => 35.5018,
            ])
            ->assertOk()
            ->assertJsonPath('data.latitude', 33.8938)
            ->assertJsonPath('data.longitude', 35.5018)
            ->assertJsonPath('data.city', 'Beirut');

        $body = $response->getContent();
        $this->assertStringNotContainsString('graphhopper', $body);
        $this->assertStringNotContainsString('test-key', $body);
    }

    public function test_reverse_geocode_still_returns_coordinate_when_provider_fails(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'down'], 503),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/reverse', [
                'latitude' => 33.8938,
                'longitude' => 35.5018,
            ])
            ->assertOk()
            ->assertJsonPath('data.latitude', 33.8938)
            ->assertJsonPath('data.longitude', 35.5018)
            ->assertJsonPath('data.label', null);
    }

    public function test_reverse_geocode_rejects_invalid_latitude(): void
    {
        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/reverse', [
                'latitude' => 120,
                'longitude' => 35.5,
            ])
            ->assertStatus(422);
    }

    public function test_search_geocode_returns_results(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response([
                'hits' => [[
                    'name' => 'Damascus',
                    'city' => 'Damascus',
                    'country' => 'Syria',
                    'point' => ['lat' => 33.5138, 'lng' => 36.2765],
                ]],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_code', 'SUCCESS_WITH_RESULTS')
            ->assertJsonPath('data.results.0.city', 'Damascus')
            ->assertJsonPath('data.results.0.latitude', 33.5138);
    }

    public function test_search_geocode_returns_genuine_empty_results(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['hits' => []], 200),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'NonExistentPlace12345',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_code', 'SUCCESS_EMPTY')
            ->assertJsonPath('data.results', []);
    }

    public function test_search_geocode_returns_rate_limited_on_http_429(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'API limit reached'], 429),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertStatus(429)
            ->assertJsonPath('errors.status_code', 'RATE_LIMITED');
    }

    public function test_search_geocode_returns_provider_unavailable_on_http_401(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertStatus(503)
            ->assertJsonPath('errors.status_code', 'PROVIDER_UNAVAILABLE');
    }

    public function test_search_geocode_returns_provider_unavailable_on_http_403(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertStatus(503)
            ->assertJsonPath('errors.status_code', 'PROVIDER_UNAVAILABLE');
    }

    public function test_search_geocode_returns_provider_unavailable_on_http_500(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertStatus(503)
            ->assertJsonPath('errors.status_code', 'PROVIDER_UNAVAILABLE');
    }

    public function test_search_geocode_returns_provider_unavailable_on_network_timeout(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', [
                'query' => 'Damascus',
            ])
            ->assertStatus(503)
            ->assertJsonPath('errors.status_code', 'PROVIDER_UNAVAILABLE');
    }

    public function test_search_geocode_caches_successful_query(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::response([
                'hits' => [[
                    'name' => 'Damascus',
                    'city' => 'Damascus',
                    'country' => 'Syria',
                    'point' => ['lat' => 33.5138, 'lng' => 36.2765],
                ]],
            ], 200),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        // First request - hits provider
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', ['query' => 'Damascus'])
            ->assertOk();

        // Second request - served from cache (Http::fake sequence assertion)
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', ['query' => ' DAMASCUS '])
            ->assertOk()
            ->assertJsonPath('data.status_code', 'SUCCESS_WITH_RESULTS');

        Http::assertSentCount(1);
    }

    public function test_search_geocode_does_not_cache_provider_failures(): void
    {
        Http::fake([
            'graphhopper.com/*' => Http::sequence()
                ->push(['message' => 'Quota exceeded'], 429)
                ->push([
                    'hits' => [[
                        'name' => 'FailQuery Fixed',
                        'city' => 'Beirut',
                        'country' => 'Lebanon',
                        'point' => ['lat' => 33.89, 'lng' => 35.50],
                    ]],
                ], 200),
        ]);

        $company = Company::factory()->create();
        $client = $this->makeClient($company);

        // First request fails with 429
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', ['query' => 'FailQuery'])
            ->assertStatus(429);

        // Second request should attempt provider again and succeed (not return cached error)
        $this->actingAs($client, 'api')
            ->postJson('/api/v1/client/geocode/search', ['query' => 'FailQuery'])
            ->assertOk()
            ->assertJsonPath('data.status_code', 'SUCCESS_WITH_RESULTS');
    }

    public function test_dispatcher_cannot_use_client_geocode(): void
    {
        $dispatcher = User::factory()->create([
            'role_id' => $this->dispatcherRole->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($dispatcher, 'api')
            ->postJson('/api/v1/client/geocode/reverse', [
                'latitude' => 33.89,
                'longitude' => 35.50,
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_geocode_is_rejected(): void
    {
        $this->postJson('/api/v1/client/geocode/reverse', [
            'latitude' => 33.89,
            'longitude' => 35.50,
        ])->assertUnauthorized();
    }

    private function makeClient(Company $company): User
    {
        return User::factory()->create([
            'role_id' => $this->clientRole->id,
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);
    }
}
