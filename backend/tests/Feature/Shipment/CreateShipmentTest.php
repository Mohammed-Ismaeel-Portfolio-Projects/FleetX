<?php

namespace Tests\Feature\Shipment;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class CreateShipmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeDispatcherUser();
        $this->company = Company::factory()->create();
        $this->status = Status::create(['name' => 'Draft', 'type' => 'shipment']);
    }

    public function test_can_create_shipment_with_valid_data(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/shipments', [
                'customer_company_id' => $this->company->id,
                'priority' => 'normal',
                'status_id' => $this->status->id,
                'reference_number' => 'REF-1001',
                'pickup_city' => 'Miami',
                'pickup_country' => 'US',
                'delivery_city' => 'Orlando',
                'delivery_country' => 'US',
                'planned_pickup_date' => now()->addDays(2)->toDateString(),
                'planned_delivery_date' => now()->addDays(5)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Shipment created successfully',
            ])
            ->assertJsonPath('data.customer_company_id', $this->company->id)
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.status_id', $this->status->id);

        $this->assertDatabaseHas('shipments', [
            'customer_company_id' => $this->company->id,
            'priority' => 'normal',
            'status_id' => $this->status->id,
            'reference_number' => 'REF-1001',
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/shipments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_company_id',
                'priority',
                'status_id',
            ]);
    }

    public function test_validation_fails_with_invalid_customer_company(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/shipments', [
                'customer_company_id' => '550e8400-e29b-41d4-a716-446655440000',
                'priority' => 'high',
                'status_id' => $this->status->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_company_id']);
    }
}
