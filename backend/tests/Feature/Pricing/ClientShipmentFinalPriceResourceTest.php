<?php

namespace Tests\Feature\Pricing;

use App\Enums\FinalPriceBreakdownStatus;
use App\Enums\FinalPriceCalculationTrigger;
use App\Enums\FinalPriceChangeDirection;
use App\Enums\VarianceFactorCode;
use App\Http\Resources\Client\ClientShipmentFinalPriceResource;
use App\Http\Resources\Shipment\OpsShipmentFinalPriceResource;
use App\Models\ShipmentFinalPriceBreakdown;
use App\Models\ShipmentPriceVariance;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientShipmentFinalPriceResourceTest extends TestCase
{
    public function test_client_resource_structure_uses_persisted_scalars(): void
    {
        $breakdown = $this->makeBreakdownModel();
        $breakdown->setRelation('variances', collect([
            $this->makeVariance(true, 'Distance exceeded estimate.', 75.0),
        ]));
        $breakdown->setRelation('submissionPriceBreakdown', (object) [
            'calculated_at' => now()->setDate(2026, 8, 19)->setTime(10, 0, 0),
        ]);

        $result = (new ClientShipmentFinalPriceResource($breakdown))->toArray(Request::create('/'));

        $this->assertSame('finalized', $result['status']);
        $this->assertEqualsWithDelta(1450.0, $result['submission_price']['total'], 0.01);
        $this->assertEqualsWithDelta(1610.0, $result['final_price']['total'], 0.01);
        $this->assertEqualsWithDelta(160.0, $result['comparison']['delta'], 0.01);
        $this->assertEqualsWithDelta(11.0345, $result['comparison']['delta_percentage'], 0.0001);
        $this->assertSame('increase', $result['comparison']['change_direction']);
        $this->assertTrue($result['comparison']['material_change']);
        $this->assertCount(1, $result['reasons']);
        $this->assertSame('Distance exceeded estimate.', $result['reasons'][0]['message']);
        $this->assertArrayHasKey('charges_summary', $result);
    }

    public function test_client_resource_degraded_when_breakdown_json_malformed(): void
    {
        $breakdown = $this->makeBreakdownModel(['breakdown_json' => ['bad' => true]]);
        $breakdown->setRelation('variances', collect());

        $result = (new ClientShipmentFinalPriceResource($breakdown))->toArray(Request::create('/'));

        $this->assertSame(['Final price detail unavailable'], $result['warnings']);
        $this->assertArrayNotHasKey('charges_summary', $result);
        $this->assertEqualsWithDelta(1610.0, $result['final_price']['total'], 0.01);
    }

    public function test_client_resource_does_not_expose_internal_keys(): void
    {
        $breakdown = $this->makeBreakdownModel();
        $breakdown->setRelation('variances', collect([
            $this->makeVariance(true, 'Customer safe message', 75.0, 'Internal only'),
        ]));

        $encoded = json_encode((new ClientShipmentFinalPriceResource($breakdown))->toArray(Request::create('/')));

        $this->assertStringNotContainsString('Internal only', $encoded);
        $this->assertStringNotContainsString('pricing_policy_id', $encoded);
        $this->assertStringNotContainsString('threshold_value', $encoded);
    }

    public function test_ops_resource_exposes_audit_fields(): void
    {
        $breakdown = $this->makeBreakdownModel();
        $breakdown->setRelation('variances', collect([
            $this->makeVariance(true, 'Customer message', 75.0, 'Internal message'),
        ]));
        $breakdown->setRelation('pricingPolicy', (object) ['id' => 7, 'name' => 'Frozen Policy']);
        $breakdown->setRelation('operationalActuals', (object) [
            'id' => 99,
            'capture_source' => 'ops_manual',
        ]);

        $result = (new OpsShipmentFinalPriceResource($breakdown))->toArray(Request::create('/'));

        $this->assertSame(7, $result['pricing_policy_id']);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertSame('Internal message', $result['variances'][0]['internal_message']);
    }

    private function makeBreakdownModel(array $overrides = []): ShipmentFinalPriceBreakdown
    {
        return new ShipmentFinalPriceBreakdown(array_merge([
            'id' => 1,
            'shipment_id' => '00000000-0000-0000-0000-000000000001',
            'shipment_order_id' => '00000000-0000-0000-0000-000000000002',
            'submission_price_breakdown_id' => 10,
            'pricing_policy_id' => 7,
            'operational_actuals_id' => 99,
            'breakdown_json' => [
                'finalTotal' => 1610.0,
                'freightCharge' => 1200.0,
                'subtotal' => 1500.0,
                'discount' => 75.0,
                'discountType' => 'percentage',
                'discountRate' => 5.0,
                'afterDiscount' => 1425.0,
                'tax' => 185.0,
                'taxRate' => 8.0,
                'serviceItems' => [],
            ],
            'submission_total' => 1450.00,
            'final_total' => 1610.00,
            'price_delta' => 160.00,
            'price_delta_percentage' => 11.0345,
            'change_direction' => FinalPriceChangeDirection::INCREASE,
            'material_change' => true,
            'currency' => 'USD',
            'calculated_at' => now(),
            'calculation_trigger' => FinalPriceCalculationTrigger::MANUAL,
            'status' => FinalPriceBreakdownStatus::FINALIZED,
        ], $overrides));
    }

    private function makeVariance(
        bool $material,
        ?string $customerMessage,
        float $priceImpact,
        ?string $internalMessage = null,
    ): ShipmentPriceVariance {
        return new ShipmentPriceVariance([
            'factor_code' => VarianceFactorCode::DISTANCE,
            'material' => $material,
            'billable' => $material,
            'price_impact' => $priceImpact,
            'reason_code' => 'DISTANCE_EXCEEDED',
            'customer_message' => $customerMessage,
            'internal_message' => $internalMessage,
            'sort_order' => 0,
        ]);
    }
}
