<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\Shipment;
use App\Models\Status;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;

class ChangeShipmentStatusAction
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionAction
    ) {}

    public function execute(Shipment $shipment, int $statusId, int $userId, ?string $actualPickupDate = null, ?string $actualDeliveryDate = null): ApiResult
    {
        return DB::transaction(function () use ($shipment, $statusId, $userId, $actualPickupDate, $actualDeliveryDate) {
            try {
                $targetStatus = Status::query()->find($statusId);

                if ($targetStatus === null || $targetStatus->type !== ShipmentStatus::TYPE) {
                    return ApiResult::error('Target status is not a valid shipment status', 422);
                }

                $updatedShipment = $this->transitionAction->execute(
                    $shipment,
                    $targetStatus->name,
                    $userId,
                    $actualPickupDate,
                    $actualDeliveryDate
                );

                return ApiResult::success($updatedShipment, 'Shipment status changed successfully');
            } catch (DomainException $e) {
                return ApiResult::error($e->getMessage(), $e->getCode() ?: 422, $e->getContext());
            }
        });
    }
}
