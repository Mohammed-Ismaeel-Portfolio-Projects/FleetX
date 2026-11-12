<?php

namespace Tests\Feature\Driver;

use App\Models\Driver;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->actingAs(User::factory()->create(['role_id' => $adminRole->id]));
    }

    public function test_can_create_driver_with_valid_data(): void
    {
        $status = Status::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12345',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2020-01-01',
            'license_number' => 'DL-12345678',
            'license_class' => 'CE',
            'license_expiry_date' => '2030-01-01',
            'medical_certificate_expiry' => '2029-01-01',
            'years_of_experience' => 5,
            'status_id' => $status->id,
            'is_active' => true,
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+1234567891',
            'notes' => 'Test driver',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'employee_number',
                    'first_name',
                    'last_name',
                    'full_name',
                    'email',
                    'phone',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('drivers', [
            'employee_number' => 'EMP-12345',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_cannot_create_driver_with_duplicate_employee_number(): void
    {
        Driver::factory()->create(['employee_number' => 'EMP-12345']);
        $status = Status::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12345',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe2@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2020-01-01',
            'license_number' => 'DL-12345679',
            'license_class' => 'CE',
            'license_expiry_date' => '2030-01-01',
            'status_id' => $status->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_number']);
    }

    public function test_cannot_create_driver_with_duplicate_email(): void
    {
        Driver::factory()->create(['email' => 'john.doe@example.com']);
        $status = Status::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12346',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2020-01-01',
            'license_number' => 'DL-12345679',
            'license_class' => 'CE',
            'license_expiry_date' => '2030-01-01',
            'status_id' => $status->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_create_driver_with_duplicate_license_number(): void
    {
        Driver::factory()->create(['license_number' => 'DL-12345678']);
        $status = Status::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12346',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe2@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2020-01-01',
            'license_number' => 'DL-12345678',
            'license_class' => 'CE',
            'license_expiry_date' => '2030-01-01',
            'status_id' => $status->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_number']);
    }

    public function test_cannot_create_driver_with_future_employment_date(): void
    {
        $status = Status::factory()->create(['type' => 'driver']);
        $futureDate = now()->addDay()->toDateString();

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12346',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => $futureDate,
            'license_number' => 'DL-12345678',
            'license_class' => 'CE',
            'license_expiry_date' => '2030-01-01',
            'status_id' => $status->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employment_date']);
    }

    public function test_cannot_create_driver_with_past_license_expiry(): void
    {
        $status = Status::factory()->create(['type' => 'driver']);
        $pastDate = now()->subDay()->toDateString();

        $response = $this->postJson('/api/v1/drivers', [
            'employee_number' => 'EMP-12346',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'nationality' => 'American',
            'date_of_birth' => '1990-01-01',
            'employment_date' => '2020-01-01',
            'license_number' => 'DL-12345678',
            'license_class' => 'CE',
            'license_expiry_date' => $pastDate,
            'status_id' => $status->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_expiry_date']);
    }
}
