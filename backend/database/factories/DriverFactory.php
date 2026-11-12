<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $availableStatus = Status::firstOrCreate(
            ['name' => 'Available', 'type' => 'driver'],
            ['name' => 'Available', 'type' => 'driver']
        );

        return [
            'employee_number' => sprintf('EMP-%05d', fake()->unique()->numberBetween(1000, 99999)),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'nationality' => fake()->country(),
            'phone' => fake()->unique()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'employment_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'license_number' => strtoupper(fake()->bothify('DL-########')),
            'license_class' => fake()->randomElement(['B', 'C', 'C1', 'CE']),
            'license_expiry_date' => fake()->dateTimeBetween('+6 months', '+5 years')->format('Y-m-d'),
            'medical_certificate_expiry' => fake()->optional()->dateTimeBetween('+3 months', '+2 years')?->format('Y-m-d'),
            'years_of_experience' => fake()->numberBetween(1, 25),
            'status_id' => $availableStatus->id,
            'is_active' => true,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public static function driverStatusId(string $statusName): int
    {
        return Status::where('name', $statusName)->where('type', 'driver')->firstOrFail()->id;
    }

    public function active(int $statusId): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $statusId,
            'is_active' => true,
        ]);
    }

    public function inactive(int $statusId): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $statusId,
            'is_active' => false,
        ]);
    }
}
