<?php

namespace Tests\Feature\Assignment;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDispatcherUser;
use Tests\TestCase;

class CreateAssignmentTest extends TestCase
{
    use CreatesDispatcherUser;
    use RefreshDatabase;

    private User $user;
    private User $assignedByUser;
    private Shipment $shipment;
    private Driver $driver;
    private Vehicle $vehicle;
    private Trailer $trailer;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeDispatcherUser();
        $this->assignedByUser = $this->makeDispatcherUser();
        $this->shipment = Shipment::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();
        $this->trailer = Trailer::factory()->create();
        $this->status = Status::create(['name' => 'Assigned', 'type' => 'assignment']);
    }

    public function test_can_create_assignment_with_valid_data(): void
    {
        $data = $this->validPayload();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment created successfully',
            ]);

        $this->assertDatabaseHas('assignments', [
            'shipment_id' => $this->shipment->id,
            'assignment_code' => 'ASS-00001',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => $this->trailer->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_can_create_assignment_without_vehicle_and_trailer(): void
    {
        $data = $this->validPayload([
            'vehicle_id' => null,
            'trailer_id' => null,
        ]);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment created successfully',
            ]);

        $this->assertDatabaseHas('assignments', [
            'shipment_id' => $this->shipment->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => null,
            'trailer_id' => null,
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipment_id',
                'assignment_code',
                'driver_id',
                'status_id',
                'assigned_at',
            ])
            ->assertJsonMissingValidationErrors(['assigned_by']);
    }

    public function test_client_supplied_assigned_by_is_prohibited(): void
    {
        $spoofed = $this->makeDispatcherUser();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'assigned_by' => $spoofed->id,
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_by']);
    }

    public function test_assigned_by_is_the_authenticated_dispatcher(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'assignment_code' => 'ASS-AUTH-BY',
            ]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('assignments', [
            'assignment_code' => 'ASS-AUTH-BY',
            'assigned_by' => $this->user->id,
            'driver_id' => $this->driver->id,
        ]);

        $this->assertDatabaseMissing('assignments', [
            'assignment_code' => 'ASS-AUTH-BY',
            'assigned_by' => $this->assignedByUser->id,
        ]);
    }

    public function test_validation_fails_with_duplicate_assignment_code(): void
    {
        Assignment::factory()->create(['assignment_code' => 'ASS-00001']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload(['assignment_code' => 'ASS-00001']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['assignment_code']);
    }

    public function test_validation_fails_when_driver_does_not_exist(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'driver_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_validation_fails_when_shipment_does_not_exist(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'shipment_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shipment_id']);
    }

    public function test_validation_fails_when_vehicle_does_not_exist(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'vehicle_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle_id']);
    }

    public function test_validation_fails_when_trailer_does_not_exist(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'trailer_id' => '550e8400-e29b-41d4-a716-446655440000',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trailer_id']);
    }

    public function test_validation_fails_with_ended_at_before_assigned_at(): void
    {
        $assignedAt = now();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'assigned_at' => $assignedAt->toDateTimeString(),
                'ended_at' => $assignedAt->subDay()->toDateTimeString(),
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ended_at']);
    }

    public function test_cannot_have_multiple_active_assignments_for_same_shipment(): void
    {
        // Create first active assignment
        Assignment::factory()->create([
            'shipment_id' => $this->shipment->id,
            'is_active' => true,
        ]);

        // Attempt to create second active assignment for same shipment
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload([
                'shipment_id' => $this->shipment->id,
                'is_active' => true,
            ]));

        // Should fail with business rule validation
        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to create assignment: A shipment cannot have multiple active assignments.',
            ]);
    }

    public function test_response_includes_shipment_object(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/assignments', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'shipment_id',
                    'shipment' => [
                        'id',
                        'shipment_number',
                        'reference_number',
                    ],
                ],
            ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'shipment_id' => $this->shipment->id,
            'assignment_code' => 'ASS-00001',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'trailer_id' => $this->trailer->id,
            'status_id' => $this->status->id,
            'assigned_at' => now()->toDateTimeString(),
            'ended_at' => null,
            'is_active' => true,
            'notes' => 'Test assignment',
        ], $overrides);
    }
}
