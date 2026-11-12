<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\Company;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $draftStatus = Status::firstOrCreate(
            ['name' => 'Draft', 'type' => 'shipment'],
            ['name' => 'Draft', 'type' => 'shipment']
        );

        /** @var \App\Services\TrackingSimulator\EuropeanFleetLocations $fleetLocations */
        $fleetLocations = app(\App\Services\TrackingSimulator\EuropeanFleetLocations::class);
        $pair = $fleetLocations->randomShipmentPair();

        return [
            'shipment_number' => sprintf('SHP-%05d', fake()->unique()->numberBetween(1000, 99999)),
            'customer_company_id' => Company::inRandomOrder()->first()?->id ?? Company::factory(),
            'reference_number' => fake()->optional()->numerify('REF-########'),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'status_id' => $draftStatus->id,
            'cargo_type' => fake()->randomElement(['General Cargo', 'Perishable', 'Hazardous', 'Fragile', 'Oversized']),
            'cargo_description' => fake()->optional()->sentence(),
            'cargo_weight' => fake()->optional()->randomFloat(2, 100, 50000),
            'cargo_volume' => fake()->optional()->randomFloat(2, 1, 1000),
            'package_count' => fake()->optional()->numberBetween(1, 500),
            'hazardous_cargo' => fake()->boolean(10),
            'temperature_controlled' => fake()->boolean(20),
            'pickup_company' => fake()->company(),
            'pickup_contact' => fake()->name(),
            'pickup_phone' => fake()->phoneNumber(),
            'pickup_address' => fake()->streetAddress(),
            'pickup_city' => $pair['pickup_city'],
            'pickup_country' => $pair['pickup_country'],
            'delivery_company' => fake()->company(),
            'delivery_contact' => fake()->name(),
            'delivery_phone' => fake()->phoneNumber(),
            'delivery_address' => fake()->streetAddress(),
            'delivery_city' => $pair['delivery_city'],
            'delivery_country' => $pair['delivery_country'],
            'planned_pickup_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'planned_delivery_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 months'),
            'actual_pickup_date' => null,
            'actual_delivery_date' => null,
            'special_instructions' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public static function shipmentStatusId(string $statusName): int
    {
        return Status::where('name', $statusName)->where('type', 'shipment')->firstOrFail()->id;
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Draft'),
        ]);
    }

    public function pendingReview(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Pending Review'),
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Approved'),
        ]);
    }

    public function waitingAssignment(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Waiting Assignment'),
        ]);
    }

    public function assigned(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Assigned'),
        ]);
    }

    public function readyForPickup(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Ready For Pickup'),
        ]);
    }

    public function pickedUp(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Picked Up'),
            'actual_pickup_date' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function inTransit(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('In Transit'),
            'actual_pickup_date' => fake()->dateTimeBetween('-2 weeks', '-1 week'),
        ]);
    }

    public function atCustoms(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('At Customs'),
            'actual_pickup_date' => fake()->dateTimeBetween('-3 weeks', '-2 weeks'),
        ]);
    }

    public function outForDelivery(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Out For Delivery'),
            'actual_pickup_date' => fake()->dateTimeBetween('-4 weeks', '-3 weeks'),
        ]);
    }

    public function delivered(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Delivered'),
            'actual_pickup_date' => fake()->dateTimeBetween('-2 months', '-1 month'),
            'actual_delivery_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Completed'),
            'actual_pickup_date' => fake()->dateTimeBetween('-3 months', '-2 months'),
            'actual_delivery_date' => fake()->dateTimeBetween('-2 months', '-1 month'),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => self::shipmentStatusId('Cancelled'),
        ]);
    }

    public function forCustomer(string $companyId): self
    {
        return $this->state(fn (array $attributes) => [
            'customer_company_id' => $companyId,
        ]);
    }

    public function highPriority(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    public function urgent(): self
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }
}
