<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Enums\CargoType;
use App\Enums\CustomerType;
use App\Enums\TrailerType;
use App\Services\Pricing\PricingInput;
use App\Services\Pricing\PricingInputFactory;
use PHPUnit\Framework\TestCase;

class PricingInputFactoryTest extends TestCase
{
    private PricingInputFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PricingInputFactory();
    }

    public function test_creates_valid_pricing_input(): void
    {
        $input = $this->factory->create(
            distance: 500.0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 15.0,
            shipmentPriority: 'standard',
            customerType: CustomerType::CORPORATE,
            resolvedServices: [['id' => '1', 'name' => 'GPS Seal', 'price' => 15.0]],
            borderCrossing: true,
            hazmat: false,
            insuranceRequired: true,
            loadingWaitingTime: 30,
            unloadingWaitingTime: 20,
        );

        $this->assertInstanceOf(PricingInput::class, $input);
        $this->assertEquals(500.0, $input->distance);
        $this->assertEquals(CargoType::GENERAL, $input->cargoType);
        $this->assertEquals(TrailerType::DRY_VAN, $input->trailerType);
        $this->assertEquals(15.0, $input->cargoWeight);
        $this->assertEquals('standard', $input->shipmentPriority);
        $this->assertTrue($input->borderCrossing);
        $this->assertFalse($input->hazmat);
        $this->assertTrue($input->insuranceRequired);
        $this->assertEquals(30, $input->loadingWaitingTime);
        $this->assertEquals(20, $input->unloadingWaitingTime);
    }

    public function test_customer_type_enum_passed_as_value_string(): void
    {
        $input = $this->factory->create(
            distance: 100.0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10.0,
            shipmentPriority: 'standard',
            customerType: CustomerType::VIP,
        );

        $this->assertEquals('vip', $input->customerType);
    }

    public function test_all_customer_types_accepted(): void
    {
        foreach (CustomerType::cases() as $type) {
            $input = $this->factory->create(
                distance: 100.0,
                cargoType: CargoType::GENERAL,
                trailerType: TrailerType::DRY_VAN,
                cargoWeight: 10.0,
                shipmentPriority: 'standard',
                customerType: $type,
            );

            $this->assertEquals($type->value, $input->customerType);
        }
    }

    public function test_server_derived_distance_preserved(): void
    {
        $input = $this->factory->create(
            distance: 1234.56,
            cargoType: CargoType::HEAVY,
            trailerType: TrailerType::LOWBOY,
            cargoWeight: 30.0,
            shipmentPriority: 'express',
            customerType: CustomerType::STANDARD,
        );

        $this->assertEquals(1234.56, $input->distance);
    }

    public function test_zero_distance_accepted(): void
    {
        $input = $this->factory->create(
            distance: 0.0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 5.0,
            shipmentPriority: 'standard',
            customerType: CustomerType::STANDARD,
        );

        $this->assertEquals(0.0, $input->distance);
    }

    public function test_resolved_services_preserved(): void
    {
        $services = [
            ['id' => '10', 'name' => 'Loading Crew', 'price' => 250.0],
            ['id' => '11', 'name' => 'GPS Seal', 'price' => 15.0],
        ];

        $input = $this->factory->create(
            distance: 300.0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10.0,
            shipmentPriority: 'standard',
            customerType: CustomerType::CORPORATE,
            resolvedServices: $services,
        );

        $this->assertCount(2, $input->selectedServices);
        $this->assertEquals('Loading Crew', $input->selectedServices[0]['name']);
        $this->assertEquals(250.0, $input->selectedServices[0]['price']);
        $this->assertEquals('GPS Seal', $input->selectedServices[1]['name']);
        $this->assertEquals(15.0, $input->selectedServices[1]['price']);
    }

    public function test_empty_services_accepted(): void
    {
        $input = $this->factory->create(
            distance: 100.0,
            cargoType: CargoType::GENERAL,
            trailerType: TrailerType::DRY_VAN,
            cargoWeight: 10.0,
            shipmentPriority: 'standard',
            customerType: CustomerType::STANDARD,
        );

        $this->assertEmpty($input->selectedServices);
    }

    public function test_defaults_applied_correctly(): void
    {
        $input = $this->factory->create(
            distance: 200.0,
            cargoType: CargoType::REFRIGERATED,
            trailerType: TrailerType::REEFER,
            cargoWeight: 18.0,
            shipmentPriority: 'urgent',
            customerType: CustomerType::LONG_TERM,
        );

        $this->assertFalse($input->borderCrossing);
        $this->assertFalse($input->hazmat);
        $this->assertFalse($input->insuranceRequired);
        $this->assertEquals(0, $input->loadingWaitingTime);
        $this->assertEquals(0, $input->unloadingWaitingTime);
        $this->assertEquals(0, $input->trafficDelay);
        $this->assertEquals(0, $input->customerDelay);
    }
}
