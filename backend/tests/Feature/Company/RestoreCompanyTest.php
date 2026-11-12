<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestoreCompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_restore_deleted_company(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
        ]);

        $company->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company restored successfully',
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'Acme Logistics Inc.',
        ]);

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'deleted_at' => null,
        ]);
    }

    public function test_returns_success_when_already_active(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Active Company',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company is already active',
            ]);
    }

    public function test_returns_404_for_non_existent_company(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$nonExistentId}/restore");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Company not found',
            ]);
    }

    public function test_restore_sets_updated_by(): void
    {
        $company = Company::factory()->create();
        $company->delete();

        $response = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals($this->user->id, $company->updated_by);
    }

    public function test_restored_company_visible_in_normal_queries(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'To Be Restored',
        ]);

        Company::factory()->create([
            'company_name' => 'Always Active',
        ]);

        $company->delete();

        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies');

        $response->assertStatus(200);
        $companyNames = collect($response->json('data.data'))->pluck('company_name');
        $this->assertContains('To Be Restored', $companyNames);
        $this->assertContains('Always Active', $companyNames);
    }

    public function test_can_restore_company_with_original_data_intact(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'contact@acme.com',
            'country' => 'US',
        ]);

        $company->delete();

        $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'contact@acme.com',
            'country' => 'US',
        ]);
    }

    public function test_can_restore_company_multiple_times(): void
    {
        $company = Company::factory()->create();

        $company->delete();

        $response1 = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company restored successfully',
            ]);

        $response2 = $this->actingAs($this->user, 'api')
            ->patchJson("/api/v1/companies/{$company->id}/restore");

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company is already active',
            ]);
    }
}
