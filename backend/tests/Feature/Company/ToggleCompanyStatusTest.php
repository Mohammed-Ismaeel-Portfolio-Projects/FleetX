<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleCompanyStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_activate_company(): void
    {
        $company = Company::factory()->create([
            'is_active' => false,
        ]);

        $data = [
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company activated successfully',
                'data' => [
                    'id' => $company->id,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_company(): void
    {
        $company = Company::factory()->create([
            'is_active' => true,
        ]);

        $data = [
            'is_active' => false,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company deactivated successfully',
                'data' => [
                    'id' => $company->id,
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'is_active' => false,
        ]);
    }

    public function test_returns_success_when_already_active(): void
    {
        $company = Company::factory()->create([
            'is_active' => true,
        ]);

        $data = [
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company already has the requested status',
                'data' => [
                    'id' => $company->id,
                    'is_active' => true,
                ],
            ]);
    }

    public function test_returns_success_when_already_inactive(): void
    {
        $company = Company::factory()->create([
            'is_active' => false,
        ]);

        $data = [
            'is_active' => false,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company already has the requested status',
                'data' => [
                    'id' => $company->id,
                    'is_active' => false,
                ],
            ]);
    }

    public function test_validation_fails_without_is_active_field(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_validation_fails_with_invalid_is_active_type(): void
    {
        $company = Company::factory()->create();

        $data = [
            'is_active' => 'not-a-boolean',
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/status", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_returns_404_for_non_existent_company(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $data = [
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$nonExistentId}/status", $data);

        $response->assertStatus(404);
    }
}
