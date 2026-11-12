<?php

namespace Tests\Feature\Pricing;

use App\Http\Requests\Client\CalculatePricingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CalculatePricingRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = CalculatePricingRequest::create('/api/v1/client/pricing/calculate', 'POST', $data);
        $request->setContainer(app());

        // Simulate prepareForValidation boolean casting
        foreach (['border_crossing', 'hazmat', 'insurance_required'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return Validator::make($data, $request->rules(), $request->messages());
    }

    private function validPayload(): array
    {
        return [
            'pickup_latitude' => 33.8869,
            'pickup_longitude' => 35.5131,
            'delivery_latitude' => 36.2021,
            'delivery_longitude' => 37.1343,
            'cargo_type' => 'general',
            'trailer_type' => 'dry_van',
            'cargo_weight' => 15.0,
            'shipment_priority' => 'standard',
        ];
    }

    public function test_valid_request_passes(): void
    {
        $v = $this->validate($this->validPayload());
        $this->assertTrue($v->passes(), implode('; ', $v->errors()->all()));
    }

    public function test_valid_request_with_all_optional_fields(): void
    {
        $v = $this->validate(array_merge($this->validPayload(), [
            'border_crossing' => true,
            'hazmat' => false,
            'insurance_required' => true,
            'loading_waiting_time' => 60,
            'unloading_waiting_time' => 30,
            'traffic_delay' => 15,
            'customer_delay' => 10,
            'selected_service_ids' => [1, 2, 3],
        ]));
        $this->assertTrue($v->passes(), implode('; ', $v->errors()->all()));
    }

    public function test_missing_pickup_latitude_rejected(): void
    {
        $data = $this->validPayload();
        unset($data['pickup_latitude']);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('pickup_latitude', $v->errors()->toArray());
    }

    public function test_missing_delivery_longitude_rejected(): void
    {
        $data = $this->validPayload();
        unset($data['delivery_longitude']);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_latitude_outside_range_rejected(): void
    {
        $data = $this->validPayload();
        $data['pickup_latitude'] = 91.0;
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_longitude_outside_range_rejected(): void
    {
        $data = $this->validPayload();
        $data['delivery_longitude'] = -181.0;
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_negative_cargo_weight_rejected(): void
    {
        $data = $this->validPayload();
        $data['cargo_weight'] = -5;
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_negative_waiting_time_rejected(): void
    {
        $data = array_merge($this->validPayload(), ['loading_waiting_time' => -1]);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_cargo_type_rejected(): void
    {
        $data = $this->validPayload();
        $data['cargo_type'] = 'nonexistent';
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_trailer_type_rejected(): void
    {
        $data = $this->validPayload();
        $data['trailer_type'] = 'invalid_trailer';
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_invalid_priority_rejected(): void
    {
        $data = $this->validPayload();
        $data['shipment_priority'] = 'super_fast';
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_service_ids_use_integer_format(): void
    {
        $data = array_merge($this->validPayload(), [
            'selected_service_ids' => [1, 2],
        ]);
        $v = $this->validate($data);
        $this->assertTrue($v->passes());
    }

    public function test_customer_type_prohibited(): void
    {
        $data = array_merge($this->validPayload(), ['customer_type' => 'vip']);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('server-resolved', $v->errors()->first('customer_type'));
    }

    public function test_distance_prohibited(): void
    {
        $data = array_merge($this->validPayload(), ['distance' => 100]);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('server-calculated', $v->errors()->first('distance'));
    }

    public function test_pricing_policy_id_prohibited(): void
    {
        $data = array_merge($this->validPayload(), ['pricing_policy_id' => 1]);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_service_price_prohibited(): void
    {
        $data = array_merge($this->validPayload(), ['service_price' => 0.01]);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }

    public function test_tax_rate_prohibited(): void
    {
        $data = array_merge($this->validPayload(), ['tax_rate' => 5]);
        $v = $this->validate($data);
        $this->assertTrue($v->fails());
    }
}
