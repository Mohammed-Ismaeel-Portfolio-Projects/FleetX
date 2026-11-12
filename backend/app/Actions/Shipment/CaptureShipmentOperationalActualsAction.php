<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Enums\OperationalActualsCaptureSource;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipment\Exceptions\ShipmentOperationalActualsException;
use App\Services\Shipment\ShipmentOperationalActualsCaptureService;

/**
 * Ops capture of shipment operational actuals (Phase 5.5a).
 */
class CaptureShipmentOperationalActualsAction
{
    public function __construct(
        private readonly ShipmentOperationalActualsCaptureService $captureService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $user, Shipment $shipment, array $validated): ApiResult
    {
        try {
            $captureSource = OperationalActualsCaptureSource::from($validated['capture_source']);

            $actuals = collect($validated)
                ->except(['capture_source'])
                ->all();

            $record = $this->captureService->capture(
                $shipment,
                $user,
                $captureSource,
                $actuals,
            );

            return ApiResult::success($record, 'Operational actuals captured successfully.');
        } catch (ShipmentOperationalActualsException $e) {
            $errors = $e->errorCode !== null ? ['code' => $e->errorCode] : null;

            return ApiResult::error($e->getMessage(), $e->statusCode, $errors);
        }
    }
}
