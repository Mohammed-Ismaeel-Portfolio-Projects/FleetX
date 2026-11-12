<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Start review on a Shipment Request (submitted → under_review) for ops users (C2.9-B2.2).
 */
class StartReviewShipmentOrderAction
{
    public function __construct(
        private readonly TransitionShipmentOrderStatusAction $transitionAction,
        private readonly GetShipmentRequestAction $getAction
    ) {}

    public function execute(User $user, string $shipmentOrderId): ApiResult
    {
        try {
            return DB::transaction(function () use ($user, $shipmentOrderId) {
                $order = ShipmentOrder::query()
                    ->with(['status'])
                    ->whereKey($shipmentOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ApiResult::notFound('Shipment request not found');
                }

                if ($order->status === null
                    || $order->status->type !== ShipmentOrderStatus::TYPE
                    || $order->status->name !== ShipmentOrderStatus::SUBMITTED) {
                    return ApiResult::error(
                        'Shipment request cannot be moved to review in current status',
                        422
                    );
                }

                $this->transitionAction->execute(
                    $order,
                    ShipmentOrderStatus::UNDER_REVIEW,
                    $user->id
                );

                $result = $this->getAction->execute($shipmentOrderId);

                return ApiResult::success($result->data, 'Shipment request moved to under review');
            });
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}
