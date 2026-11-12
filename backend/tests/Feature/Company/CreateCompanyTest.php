<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateCompanyTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_create_company_with_valid_data(): void
    {
        $data = [
            'company_name' => 'Acme Logistics Inc.',
            'legal_name' => 'Acme Logistics Incorporated',
            'company_code' => 'ACM-001',
            'tax_number' => '123456789',
            'vat_number' => 'GB123456789',
            'business_registration_number' => 'BRN-987654',
            'industry_id' => null,
            'company_size_id' => null,
            'primary_email' => 'contact@acme.com',
            'primary_phone' => '+1-555-0123',
            'website' => 'https://acme.com',
            'country' => 'US',
            'state_province' => 'California',
            'district' => 'Los Angeles County',
            'city' => 'Los Angeles',
            'building_number' => '123',
            'address_line_1' => 'Main Street',
            'address_line_2' => 'Suite 100',
            'postal_code' => '90001',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'timezone' => 'America/Los_Angeles',
            'currency_code' => 'USD',
            'language_code' => 'en',
            'status_id' => 1,
            'is_active' => true,
            'notes' => 'Primary logistics hub',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Company created successfully',
            ]);

        $this->assertDatabaseHas('companies', [
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'contact@acme.com',
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $data = [
            'company_name' => 'Test Company',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'legal_name',
                'company_code',
                'primary_email',
                'country',
                'city',
                'address_line_1',
                'postal_code',
                'currency_code',
                'status_id',
            ]);
    }

    public function test_validation_fails_with_duplicate_company_code(): void
    {
        Company::factory()->create([
            'company_code' => 'ACM-001',
        ]);

        $data = [
            'company_name' => 'Another Company',
            'legal_name' => 'Another Company Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'another@test.com',
            'country' => 'US',
            'city' => 'New York',
            'address_line_1' => 'Broadway',
            'postal_code' => '10001',
            'currency_code' => 'USD',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_code']);
    }

    public function test_validation_fails_with_invalid_email(): void
    {
        $data = [
            'company_name' => 'Test Company',
            'legal_name' => 'Test Company Inc.',
            'company_code' => 'TST-001',
            'primary_email' => 'invalid-email',
            'country' => 'US',
            'city' => 'New York',
            'address_line_1' => 'Broadway',
            'postal_code' => '10001',
            'currency_code' => 'USD',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_email']);
    }

    public function test_validation_fails_with_invalid_country_code(): void
    {
        $data = [
            'company_name' => 'Test Company',
            'legal_name' => 'Test Company Inc.',
            'company_code' => 'TST-001',
            'primary_email' => 'test@example.com',
            'country' => 'INVALID',
            'city' => 'New York',
            'address_line_1' => 'Broadway',
            'postal_code' => '10001',
            'currency_code' => 'USD',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_validation_fails_with_invalid_currency_code(): void
    {
        $data = [
            'company_name' => 'Test Company',
            'legal_name' => 'Test Company Inc.',
            'company_code' => 'TST-001',
            'primary_email' => 'test@example.com',
            'country' => 'US',
            'city' => 'New York',
            'address_line_1' => 'Broadway',
            'postal_code' => '10001',
            'currency_code' => 'INVALID',
            'status_id' => 1,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/companies', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currency_code']);
    }
}
