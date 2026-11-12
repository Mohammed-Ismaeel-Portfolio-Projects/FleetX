<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_query_companies_with_pagination(): void
    {
        Company::factory()->count(25)->create();

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'company_name',
                            'legal_name',
                            'company_code',
                            'primary_email',
                            'country',
                            'city',
                            'is_active',
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                    ],
                    'links' => [
                        'first',
                        'last',
                        'prev',
                        'next',
                    ],
                ],
            ]);

        $this->assertEquals(10, count($response->json('data.data')));
        $this->assertEquals(25, $response->json('data.meta.total'));
    }

    public function test_can_search_companies_by_name(): void
    {
        Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
        ]);

        Company::factory()->create([
            'company_name' => 'Beta Transport Co.',
        ]);

        Company::factory()->create([
            'company_name' => 'Gamma Shipping Ltd.',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?search=Acme');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(1, count($response->json('data.data')));
        $this->assertEquals('Acme Logistics Inc.', $response->json('data.data.0.company_name'));
    }

    public function test_can_filter_companies_by_country(): void
    {
        Company::factory()->count(5)->create(['country' => 'US']);
        Company::factory()->count(3)->create(['country' => 'CA']);
        Company::factory()->count(2)->create(['country' => 'MX']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?country=US');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.data')));
    }

    public function test_can_filter_companies_by_status(): void
    {
        Company::factory()->count(5)->create(['is_active' => true]);
        Company::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?is_active=true');

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.data')));
    }

    public function test_can_filter_companies_by_city(): void
    {
        Company::factory()->count(4)->create(['city' => 'Los Angeles']);
        Company::factory()->count(3)->create(['city' => 'New York']);
        Company::factory()->count(2)->create(['city' => 'Chicago']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?city=Los Angeles');

        $response->assertStatus(200);
        $this->assertEquals(4, count($response->json('data.data')));
    }

    public function test_can_sort_companies_by_name_ascending(): void
    {
        Company::factory()->create(['company_name' => 'Zeta Corp']);
        Company::factory()->create(['company_name' => 'Alpha Inc']);
        Company::factory()->create(['company_name' => 'Beta Ltd']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?sort=company_name&direction=asc');

        $response->assertStatus(200);
        $companies = $response->json('data.data');
        $this->assertEquals('Alpha Inc', $companies[0]['company_name']);
        $this->assertEquals('Beta Ltd', $companies[1]['company_name']);
        $this->assertEquals('Zeta Corp', $companies[2]['company_name']);
    }

    public function test_can_sort_companies_by_name_descending(): void
    {
        Company::factory()->create(['company_name' => 'Zeta Corp']);
        Company::factory()->create(['company_name' => 'Alpha Inc']);
        Company::factory()->create(['company_name' => 'Beta Ltd']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?sort=company_name&direction=desc');

        $response->assertStatus(200);
        $companies = $response->json('data.data');
        $this->assertEquals('Zeta Corp', $companies[0]['company_name']);
        $this->assertEquals('Beta Ltd', $companies[1]['company_name']);
        $this->assertEquals('Alpha Inc', $companies[2]['company_name']);
    }

    public function test_can_combine_filters_and_search(): void
    {
        Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
            'country' => 'US',
            'is_active' => true,
        ]);

        Company::factory()->create([
            'company_name' => 'Acme Transport Co.',
            'country' => 'CA',
            'is_active' => true,
        ]);

        Company::factory()->create([
            'company_name' => 'Beta Logistics Inc.',
            'country' => 'US',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?search=Acme&country=US&is_active=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.data')));
        $this->assertEquals('Acme Logistics Inc.', $response->json('data.data.0.company_name'));
    }

    public function test_returns_empty_list_when_no_companies_match_filters(): void
    {
        Company::factory()->count(5)->create(['country' => 'US']);

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies?country=XX');

        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data.data')));
    }
}
