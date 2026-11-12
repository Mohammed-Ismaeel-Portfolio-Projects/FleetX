<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_update_company_with_valid_data(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Old Name',
            'primary_email' => 'old@example.com',
        ]);

        $data = [
            'company_name' => 'New Name',
            'primary_email' => 'new@example.com',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company updated successfully',
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'New Name',
            'primary_email' => 'new@example.com',
        ]);
    }

    public function test_returns_no_changes_message_when_unchanged(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Test Company',
            'primary_email' => 'test@example.com',
        ]);

        $data = [
            'company_name' => 'Test Company',
            'primary_email' => 'test@example.com',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No changes detected',
            ]);
    }

    public function test_validation_fails_with_duplicate_legal_name(): void
    {
        Company::factory()->create([
            'legal_name' => 'Existing Legal Name',
        ]);

        $company = Company::factory()->create([
            'legal_name' => 'Original Legal Name',
        ]);

        $data = [
            'legal_name' => 'Existing Legal Name',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'A company with this legal name already exists',
            ]);
    }

    public function test_validation_fails_with_invalid_email(): void
    {
        $company = Company::factory()->create();

        $data = [
            'primary_email' => 'invalid-email',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_email']);
    }

    public function test_validation_fails_with_invalid_country_code(): void
    {
        $company = Company::factory()->create();

        $data = [
            'country' => 'INVALID',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_can_update_multiple_fields(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Old Name',
            'primary_email' => 'old@example.com',
            'country' => 'US',
            'city' => 'New York',
        ]);

        $data = [
            'company_name' => 'New Name',
            'primary_email' => 'new@example.com',
            'country' => 'CA',
            'city' => 'Toronto',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'New Name',
            'primary_email' => 'new@example.com',
            'country' => 'CA',
            'city' => 'Toronto',
        ]);
    }

    public function test_allows_duplicate_company_code_for_different_company(): void
    {
        Company::factory()->create([
            'company_code' => 'ACM-001',
        ]);

        $company = Company::factory()->create([
            'company_code' => 'TST-001',
        ]);

        $data = [
            'company_code' => 'ACM-001',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$company->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_code']);
    }

    public function test_returns_404_for_non_existent_company(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $data = [
            'company_name' => 'New Name',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->putJson("/api/v1/companies/{$nonExistentId}", $data);

        $response->assertStatus(404);
    }
}
