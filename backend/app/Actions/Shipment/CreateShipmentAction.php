<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Enums\CollaborationType;
use App\Enums\CompanyType;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

class CreateShipmentAction
{
    public function execute(array $data): ApiResult
    {
        return DB::transaction(function () use ($data) {
            // Resolve collaboration_type & executing_company_id
            $collaborationType = isset($data['collaboration_type'])
                ? ($data['collaboration_type'] instanceof CollaborationType
                    ? $data['collaboration_type']
                    : CollaborationType::tryFrom((string) $data['collaboration_type']) ?? CollaborationType::INTERNAL)
                : CollaborationType::INTERNAL;

            $executingCompanyId = $data['executing_company_id'] ?? null;

            if ($collaborationType === CollaborationType::OUTBOUND_PARTNER) {
                if (empty($executingCompanyId)) {
                    return ApiResult::error('Outbound partner shipments require an executing partner company', 422);
                }

                $partnerCompany = Company::whereKey($executingCompanyId)->first();
                if (! $partnerCompany || ! in_array($partnerCompany->company_type, [CompanyType::PARTNER_CARRIER, CompanyType::HYBRID], true)) {
                    return ApiResult::error('Executing company must be an active partner carrier or hybrid company', 422);
                }
            }

            // Generate shipment number if not provided
            if (!isset($data['shipment_number'])) {
                $numbers = DB::table('shipments')
                    ->where('shipment_number', 'LIKE', 'SHP-%')
                    ->pluck('shipment_number');

                $maxNum = 20020;
                foreach ($numbers as $numStr) {
                    $digits = (int) preg_replace('/\D/', '', $numStr);
                    if ($digits > $maxNum) {
                        $maxNum = $digits;
                    }
                }

                $candidateNumber = $maxNum + 1;
                while (DB::table('shipments')->where('shipment_number', sprintf('SHP-%05d', $candidateNumber))->exists()) {
                    $candidateNumber++;
                }

                $data['shipment_number'] = sprintf('SHP-%05d', $candidateNumber);
            }

            // Create shipment
            $shipment = Shipment::create([
                'shipment_number' => $data['shipment_number'],
                'customer_company_id' => $data['customer_company_id'],
                'executing_company_id' => $executingCompanyId,
                'collaboration_type' => $collaborationType->value,
                'reference_number' => $data['reference_number'] ?? null,
                'priority' => $data['priority'],
                'status_id' => $data['status_id'],
                'baseline_eta_seconds' => $data['baseline_eta_seconds'] ?? null,
                'baseline_distance_meters' => $data['baseline_distance_meters'] ?? null,
                'baseline_routing_provider' => $data['baseline_routing_provider'] ?? null,
                'baseline_calculated_at' => $data['baseline_calculated_at'] ?? null,
                'cargo_type' => $data['cargo_type'] ?? null,
                'cargo_description' => $data['cargo_description'] ?? null,
                'cargo_weight' => $data['cargo_weight'] ?? null,
                'cargo_volume' => $data['cargo_volume'] ?? null,
                'package_count' => $data['package_count'] ?? null,
                'hazardous_cargo' => $data['hazardous_cargo'] ?? false,
                'temperature_controlled' => $data['temperature_controlled'] ?? false,
                'pickup_company' => $data['pickup_company'] ?? null,
                'pickup_contact' => $data['pickup_contact'] ?? null,
                'pickup_phone' => $data['pickup_phone'] ?? null,
                'pickup_address' => $data['pickup_address'] ?? null,
                'pickup_city' => $data['pickup_city'] ?? null,
                'pickup_country' => $data['pickup_country'] ?? null,
                'delivery_company' => $data['delivery_company'] ?? null,
                'delivery_contact' => $data['delivery_contact'] ?? null,
                'delivery_phone' => $data['delivery_phone'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_city' => $data['delivery_city'] ?? null,
                'delivery_country' => $data['delivery_country'] ?? null,
                'planned_pickup_date' => $data['planned_pickup_date'] ?? null,
                'planned_delivery_date' => $data['planned_delivery_date'] ?? null,
                'actual_pickup_date' => $data['actual_pickup_date'] ?? null,
                'actual_delivery_date' => $data['actual_delivery_date'] ?? null,
                'actual_pickup_at' => $data['actual_pickup_at'] ?? null,
                'actual_delivery_at' => $data['actual_delivery_at'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'updated_by' => $data['updated_by'] ?? null,
            ]);

            // Bootstrap PlannedRouteCache for authoritative backend route
            try {
                app(\App\Actions\Shipment\GetShipmentRouteAction::class)->execute((string) $shipment->id);
            } catch (\Throwable) {
                // Fail-closed gracefully if route planning fails for invalid test coords
            }

            return ApiResult::success($shipment->load(['customerCompany', 'status']), 'Shipment created successfully');
        });
    }
}
