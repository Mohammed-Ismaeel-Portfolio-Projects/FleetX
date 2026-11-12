<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $draftStatus = Status::firstOrCreate(
            ['name' => 'Draft', 'type' => 'assignment'],
            ['name' => 'Draft', 'type' => 'assignment']
        );

        return [
            'shipment_id' => Shipment::inRandomOrder()->first()?->id ?? Shipment::factory(),
            'assignment_code' => sprintf('ASN-%05d', fake()->unique()->numberBetween(1000, 99999)),
            'driver_id' => Driver::inRandomOrder()->first()?->id ?? Driver::factory(),
            'vehicle_id' => Vehicle::inRandomOrder()->first()?->id ?? Vehicle::factory(),
            'trailer_id' => Trailer::inRandomOrder()->first()?->id ?? Trailer::factory(),
            'assigned_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'status_id' => $draftStatus->id,
            'assigned_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'ended_at' => null,
            'is_active' => true,
            'engine_enrollment_status' => null,
            'engine_enrolled_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public static function assignmentStatusId(string $statusName): int
    {
        return Status::where('name', $statusName)->where('type', 'assignment')->firstOrFail()->id;
    }

    public function engineEnrolled(): static
    {
        return $this->state(fn () => [
            'engine_enrollment_status' => \App\Support\EngineEnrollmentStatus::ENROLLED,
            'engine_enrolled_at' => now(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('Draft'),
            'is_active' => false,
        ]);
    }

    public function assigned(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('Assigned'),
            'is_active' => true,
        ]);
    }

    public function ready(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('Ready'),
            'is_active' => true,
        ]);
    }

    public function inOperation(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('In Operation'),
            'is_active' => true,
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('Completed'),
            'is_active' => false,
            'ended_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::assignmentStatusId('Cancelled'),
            'is_active' => false,
            'ended_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function forDriver(int $driverId): self
    {
        return $this->state(fn (array $attributes) => [
            'driver_id' => $driverId,
        ]);
    }

    public function forShipment(string $shipmentId): self
    {
        return $this->state(fn (array $attributes) => [
            'shipment_id' => $shipmentId,
        ]);
    }

    public function forVehicle(string $vehicleId): self
    {
        return $this->state(fn (array $attributes) => [
            'vehicle_id' => $vehicleId,
        ]);
    }

    public function forTrailer(string $trailerId): self
    {
        return $this->state(fn (array $attributes) => [
            'trailer_id' => $trailerId,
        ]);
    }
}
