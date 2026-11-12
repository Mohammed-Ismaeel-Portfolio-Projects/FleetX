<?php

namespace Tests\Feature\Trailer;

use App\Models\Status;
use App\Models\User;
use App\Models\Trailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTrailerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Status $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->status = Status::create(['name' => 'Available', 'type' => 'trailer']);
    }

    public function test_can_create_trailer_with_valid_data(): void
    {
        $data = $this->validPayload();

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/trailers', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Trailer created successfully',
            ]);

        $this->assertDatabaseHas('trailers', [
            'trailer_code' => 'TR-00001',
            'fleet_number' => 'FT-1001',
            'plate_number' => 'FT-1001-US',
            'trailer_type' => 'Dry Van',
            'manufacturer' => 'Great Dane',
        ]);
    }

    public function test_validation_fails_with_missing_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/trailers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'trailer_code',
                'fleet_number',
                'plate_number',
                'trailer_type',
                'manufacturer',
                'status_id',
            ]);
    }

    public function test_validation_fails_with_duplicate_trailer_code(): void
    {
        Trailer::factory()->create(['trailer_code' => 'TR-00001']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/trailers', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trailer_code']);
    }

    public function test_validation_fails_with_duplicate_plate_number(): void
    {
        Trailer::factory()->create(['plate_number' => 'FT-1001-US']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/trailers', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_validation_fails_with_duplicate_fleet_number(): void
    {
        Trailer::factory()->create(['fleet_number' => 'FT-1001']);

        $response = $this->actingAs($this->user, 'api')
            ->postJson('/api/v1/trailers', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_number']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'trailer_code' => 'TR-00001',
            'fleet_number' => 'FT-1001',
            'plate_number' => 'FT-1001-US',
            'trailer_type' => 'Dry Van',
            'manufacturer' => 'Great Dane',
            'model' => 'SuperSeal',
            'production_year' => 2022,
            'axle_count' => 3,
            'payload_capacity_kg' => 25000,
            'length_m' => 13.7,
            'width_m' => 2.6,
            'height_m' => 2.9,
            'cargo_volume_m3' => 95.5,
            'current_odometer' => 85000,
            'gps_device_identifier' => 'GPS-FT-1001',
            'insurance_expiry_date' => now()->addMonths(10)->toDateString(),
            'inspection_expiry_date' => now()->addMonths(6)->toDateString(),
            'registration_expiry_date' => now()->addYear()->toDateString(),
            'status_id' => $this->status->id,
            'is_active' => true,
            'notes' => 'Standard dry van for general freight.',
        ], $overrides);
    }
}
