<?php

namespace Tests\Unit;

use App\Enums\CollaborationType;
use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Trailer;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerFleetDomainModelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_type_casts_and_helpers(): void
    {
        $company = Company::factory()->create([
            'company_type' => 'partner_carrier',
        ]);

        $this->assertInstanceOf(CompanyType::class, $company->company_type);
        $this->assertEquals(CompanyType::PARTNER_CARRIER, $company->company_type);
        $this->assertTrue($company->isPartnerCarrier());
        $this->assertFalse($company->isInternal());
        $this->assertFalse($company->isHybrid());
    }

    public function test_shipment_collaboration_type_casts_and_helpers(): void
    {
        $customerCompany = Company::factory()->create(['company_type' => 'client']);
        $partnerCompany = Company::factory()->create(['company_type' => 'partner_carrier']);

        $internalShipment = Shipment::factory()->create([
            'customer_company_id' => $customerCompany->id,
            'executing_company_id' => null,
            'collaboration_type' => 'internal',
        ]);

        $partnerShipment = Shipment::factory()->create([
            'customer_company_id' => $customerCompany->id,
            'executing_company_id' => $partnerCompany->id,
            'collaboration_type' => 'outbound_partner',
        ]);

        $this->assertInstanceOf(CollaborationType::class, $internalShipment->collaboration_type);
        $this->assertEquals(CollaborationType::INTERNAL, $internalShipment->collaboration_type);
        $this->assertTrue($internalShipment->isInternallyExecuted());
        $this->assertFalse($internalShipment->isPartnerExecuted());
        $this->assertNull($internalShipment->executingCompany);

        $this->assertInstanceOf(CollaborationType::class, $partnerShipment->collaboration_type);
        $this->assertEquals(CollaborationType::OUTBOUND_PARTNER, $partnerShipment->collaboration_type);
        $this->assertTrue($partnerShipment->isPartnerExecuted());
        $this->assertFalse($partnerShipment->isInternallyExecuted());
        $this->assertNotNull($partnerShipment->executingCompany);
        $this->assertEquals($partnerCompany->id, $partnerShipment->executingCompany->id);
        $this->assertEquals($customerCompany->id, $partnerShipment->customerCompany->id);
    }

    public function test_vehicle_ownership_scopes_and_helpers(): void
    {
        $partnerCompany = Company::factory()->create(['company_type' => 'partner_carrier']);

        $internalVehicle = Vehicle::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
        ]);

        $partnerVehicle = Vehicle::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
        ]);

        $this->assertTrue($internalVehicle->isInternalFleet());
        $this->assertFalse($internalVehicle->isPartnerFleet());

        $this->assertTrue($partnerVehicle->isPartnerFleet());
        $this->assertFalse($partnerVehicle->isInternalFleet());
        $this->assertEquals($partnerCompany->id, $partnerVehicle->company->id);

        $this->assertCount(1, Vehicle::internalFleet()->where('id', $internalVehicle->id)->get());
        $this->assertCount(0, Vehicle::partnerFleet()->where('id', $internalVehicle->id)->get());

        $this->assertCount(1, Vehicle::partnerFleet()->where('id', $partnerVehicle->id)->get());
        $this->assertCount(1, Vehicle::partnerFleet($partnerCompany->id)->where('id', $partnerVehicle->id)->get());
    }

    public function test_driver_ownership_scopes_and_helpers(): void
    {
        $partnerCompany = Company::factory()->create(['company_type' => 'partner_carrier']);

        $internalDriver = Driver::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
        ]);

        $partnerDriver = Driver::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
        ]);

        $this->assertTrue($internalDriver->isInternalFleet());
        $this->assertFalse($internalDriver->isPartnerFleet());

        $this->assertTrue($partnerDriver->isPartnerFleet());
        $this->assertFalse($partnerDriver->isInternalFleet());
        $this->assertEquals($partnerCompany->id, $partnerDriver->company->id);

        $this->assertCount(1, Driver::internalFleet()->where('id', $internalDriver->id)->get());
        $this->assertCount(0, Driver::partnerFleet()->where('id', $internalDriver->id)->get());

        $this->assertCount(1, Driver::partnerFleet()->where('id', $partnerDriver->id)->get());
        $this->assertCount(1, Driver::partnerFleet($partnerCompany->id)->where('id', $partnerDriver->id)->get());
    }

    public function test_trailer_ownership_scopes_and_helpers(): void
    {
        $partnerCompany = Company::factory()->create(['company_type' => 'partner_carrier']);

        $internalTrailer = Trailer::factory()->create([
            'ownership_type' => 'internal',
            'company_id' => null,
        ]);

        $partnerTrailer = Trailer::factory()->create([
            'ownership_type' => 'partner',
            'company_id' => $partnerCompany->id,
        ]);

        $this->assertTrue($internalTrailer->isInternalFleet());
        $this->assertFalse($internalTrailer->isPartnerFleet());

        $this->assertTrue($partnerTrailer->isPartnerFleet());
        $this->assertFalse($partnerTrailer->isInternalFleet());
        $this->assertEquals($partnerCompany->id, $partnerTrailer->company->id);

        $this->assertCount(1, Trailer::internalFleet()->where('id', $internalTrailer->id)->get());
        $this->assertCount(0, Trailer::partnerFleet()->where('id', $internalTrailer->id)->get());

        $this->assertCount(1, Trailer::partnerFleet()->where('id', $partnerTrailer->id)->get());
        $this->assertCount(1, Trailer::partnerFleet($partnerCompany->id)->where('id', $partnerTrailer->id)->get());
    }
}
