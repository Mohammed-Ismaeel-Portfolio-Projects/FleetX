<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowCompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_show_existing_company(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company retrieved successfully',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'company_name',
                    'legal_name',
                    'company_code',
                    'primary_email',
                    'country',
                    'city',
                    'is_active',
                ],
            ]);

        $this->assertEquals('Acme Logistics Inc.', $response->json('data.company_name'));
        $this->assertEquals('ACM-001', $response->json('data.company_code'));
    }

    public function test_returns_404_for_missing_company(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/companies/{$nonExistentId}");

        $response->assertStatus(404);
    }

    public function test_returns_company_with_all_fields(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Test Company',
            'legal_name' => 'Test Company Inc.',
            'company_code' => 'TST-001',
            'tax_number' => '123456789',
            'vat_number' => 'GB123456789',
            'primary_email' => 'test@example.com',
            'primary_phone' => '+1-555-0123',
            'website' => 'https://example.com',
            'country' => 'US',
            'city' => 'New York',
            'postal_code' => '10001',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'company_name' => 'Test Company',
                    'legal_name' => 'Test Company Inc.',
                    'company_code' => 'TST-001',
                    'tax_number' => '123456789',
                    'vat_number' => 'GB123456789',
                    'primary_email' => 'test@example.com',
                    'primary_phone' => '+1-555-0123',
                    'website' => 'https://example.com',
                    'country' => 'US',
                    'city' => 'New York',
                    'postal_code' => '10001',
                    'currency_code' => 'USD',
                    'is_active' => true,
                ],
            ]);
    }

    public function test_does_not_expose_internal_fields(): void
    {
        $company = Company::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('deleted_at', $data);
        $this->assertArrayNotHasKey('created_by', $data);
        $this->assertArrayNotHasKey('updated_by', $data);
    }
}
