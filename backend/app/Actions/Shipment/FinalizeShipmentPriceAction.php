<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Enums\FinalPriceCalculationTrigger;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Pricing\Exceptions\ShipmentFinalPricingException;
use App\Services\Pricing\ShipmentFinalPricingService;

/**
 * Ops manual final shipment pricing (Phase 5.5c).
 */
class FinalizeShipmentPriceAction
{
    public function __construct(
        private readonly ShipmentFinalPricingService $finalPricingService,
    ) {}

    public function execute(User $user, Shipment $shipment): ApiResult
    {
        try {
            $snapshot = $this->finalPricingService->finalize(
                $shipment,
                $user,
                FinalPriceCalculationTrigger::MANUAL,
            );

            return ApiResult::success($snapshot, 'Final shipment price finalized successfully.');
        } catch (ShipmentFinalPricingException $e) {
            $errors = $e->errorCode !== null ? ['code' => $e->errorCode] : null;

            return ApiResult::error($e->getMessage(), $e->statusCode, $errors);
        }
    }
}
