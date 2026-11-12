<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteCompanyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_soft_delete_company(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company deleted successfully',
            ]);

        $this->assertSoftDeleted('companies', [
            'id' => $company->id,
        ]);
    }

    public function test_returns_success_when_already_deleted(): void
    {
        $company = Company::factory()->create();
        $company->delete();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Company already deleted',
            ]);
    }

    public function test_returns_404_for_non_existent_company(): void
    {
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$nonExistentId}");

        $response->assertStatus(404);
    }

    public function test_soft_delete_sets_updated_by(): void
    {
        $company = Company::factory()->create();

        $response = $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertStatus(200);

        $company->refresh();
        $this->assertEquals($this->user->id, $company->updated_by);
    }

    public function test_company_not_visible_in_normal_queries_after_soft_delete(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'To Be Deleted',
        ]);

        Company::factory()->create([
            'company_name' => 'To Keep',
        ]);

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response = $this->actingAs($this->user, 'api')
            ->getJson('/api/v1/companies');

        $response->assertStatus(200);
        $companyNames = collect($response->json('data.data'))->pluck('company_name');
        $this->assertNotContains('To Be Deleted', $companyNames);
        $this->assertContains('To Keep', $companyNames);
    }

    public function test_soft_delete_preserves_company_data(): void
    {
        $company = Company::factory()->create([
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'contact@acme.com',
        ]);

        $this->actingAs($this->user, 'api')
            ->deleteJson("/api/v1/companies/{$company->id}");

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'company_name' => 'Acme Logistics Inc.',
            'company_code' => 'ACM-001',
            'primary_email' => 'contact@acme.com',
        ]);
    }
}
