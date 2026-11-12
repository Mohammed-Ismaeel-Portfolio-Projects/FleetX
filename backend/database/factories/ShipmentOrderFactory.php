<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentOrder>
 */
class ShipmentOrderFactory extends Factory
{
    protected $model = ShipmentOrder::class;

    public function definition(): array
    {
        $draftStatus = Status::firstOrCreate(
            ['name' => ShipmentOrderStatus::DRAFT, 'type' => ShipmentOrderStatus::TYPE],
            ['name' => ShipmentOrderStatus::DRAFT, 'type' => ShipmentOrderStatus::TYPE]
        );

        return [
            'request_number' => sprintf('REQ-%05d', fake()->unique()->numberBetween(1000, 99999)),
            'customer_company_id' => Company::factory(),
            'submitted_by_user_id' => null,
            'status_id' => $draftStatus->id,
            'submission_revision' => 0,
            'payload_json' => null,
            'reference_number' => fake()->optional()->numerify('REF-########'),
            'priority' => null,
            'cargo_type' => fake()->randomElement(['General Cargo', 'Perishable', 'Hazardous', 'Fragile']),
            'cargo_description' => fake()->sentence(),
            'cargo_weight' => fake()->randomFloat(2, 100, 50000),
            'cargo_volume' => fake()->optional()->randomFloat(2, 1, 1000),
            'package_count' => fake()->optional()->numberBetween(1, 500),
            'hazardous_cargo' => false,
            'temperature_controlled' => false,
            'pickup_company' => fake()->optional()->company(),
            'pickup_contact' => fake()->optional()->name(),
            'pickup_phone' => fake()->optional()->phoneNumber(),
            'pickup_address' => fake()->optional()->streetAddress(),
            'pickup_city' => fake()->city(),
            'pickup_country' => fake()->countryCode(),
            'pickup_latitude' => null,
            'pickup_longitude' => null,
            'delivery_company' => fake()->optional()->company(),
            'delivery_contact' => fake()->optional()->name(),
            'delivery_phone' => fake()->optional()->phoneNumber(),
            'delivery_address' => fake()->optional()->streetAddress(),
            'delivery_city' => fake()->city(),
            'delivery_country' => fake()->countryCode(),
            'delivery_latitude' => null,
            'delivery_longitude' => null,
            'requested_pickup_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'requested_delivery_date' => fake()->optional()->dateTimeBetween('+1 month', '+2 months'),
            'special_instructions' => fake()->optional()->sentence(),
            'client_notes' => null,
            'dispatcher_notes' => null,
            'converted_shipment_id' => null,
        ];
    }

    public function withStopCoordinates(): self
    {
        return $this->state(fn () => [
            'pickup_latitude' => 52.2297,
            'pickup_longitude' => 21.0122,
            'delivery_latitude' => 50.0647,
            'delivery_longitude' => 19.9450,
        ]);
    }

    public function forCustomer(string $companyId): self
    {
        return $this->state(fn () => ['customer_company_id' => $companyId]);
    }

    public function submittedBy(User $user): self
    {
        return $this->state(function () use ($user) {
            $companyId = $user->company_id ?? Company::factory()->create()->id;

            return [
                'submitted_by_user_id' => $user->id,
                'customer_company_id' => $companyId,
            ];
        });
    }

    public function withStatus(string $statusName): self
    {
        return $this->state(function () use ($statusName) {
            $status = Status::firstOrCreate(
                ['name' => $statusName, 'type' => ShipmentOrderStatus::TYPE],
                ['name' => $statusName, 'type' => ShipmentOrderStatus::TYPE]
            );

            return ['status_id' => $status->id];
        });
    }

    public function draft(): self
    {
        return $this->withStatus(ShipmentOrderStatus::DRAFT);
    }

    public function submitted(): self
    {
        return $this->withStatus(ShipmentOrderStatus::SUBMITTED);
    }

    public function converted(Shipment $shipment): self
    {
        return $this->withStatus(ShipmentOrderStatus::CONVERTED)->state(fn () => [
            'converted_shipment_id' => $shipment->id,
            'customer_company_id' => $shipment->customer_company_id,
        ]);
    }
}
