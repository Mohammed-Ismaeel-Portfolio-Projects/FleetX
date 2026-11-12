<?php

namespace App\Actions\ShipmentOrder;

use App\Actions\Notification\NotifyCompanyClientUsersAction;
use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Approve a Shipment Request (under_review → approved) for ops users (C2.9-B2.2).
 */
class ApproveShipmentOrderAction
{
    public function __construct(
        private readonly TransitionShipmentOrderStatusAction $transitionAction,
        private readonly GetShipmentRequestAction $getAction,
        private readonly NotifyCompanyClientUsersAction $notifyCompanyClients
    ) {}

    public function execute(User $user, string $shipmentOrderId, ?string $reason = null): ApiResult
    {
        $notifyCompanyId = null;
        $notifyOrderId = null;

        try {
            $result = DB::transaction(function () use ($user, $shipmentOrderId, $reason, &$notifyCompanyId, &$notifyOrderId) {
                $order = ShipmentOrder::query()
                    ->with(['status'])
                    ->whereKey($shipmentOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ApiResult::notFound('Shipment request not found');
                }

                if ($order->converted_shipment_id !== null || in_array($order->status?->name, [
                    ShipmentOrderStatus::APPROVED,
                    ShipmentOrderStatus::CONVERTED,
                    ShipmentOrderStatus::REJECTED,
                    ShipmentOrderStatus::CANCELLED,
                ], true)) {
                    return ApiResult::error(
                        'Historical shipment requests are immutable and cannot be modified.',
                        422
                    );
                }

                if ($order->status === null
                    || $order->status->type !== ShipmentOrderStatus::TYPE
                    || $order->status->name !== ShipmentOrderStatus::UNDER_REVIEW) {
                    return ApiResult::error(
                        'Shipment request cannot be approved in current status',
                        422
                    );
                }

                $this->transitionAction->execute(
                    $order,
                    ShipmentOrderStatus::APPROVED,
                    $user->id,
                    $reason
                );

                $showResult = $this->getAction->execute($shipmentOrderId);

                $notifyCompanyId = $order->customer_company_id;
                $notifyOrderId = $order->id;

                return ApiResult::success($showResult->data, 'Shipment request approved successfully');
            });
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() ?: 422);
        }

        if ($result->success) {
            $this->notifyRequestClients(
                $notifyCompanyId,
                $notifyOrderId,
                'Shipment request approved',
                'Your shipment request has been approved by FleetX.'
            );
        }

        return $result;
    }

    private function notifyRequestClients(mixed $companyId, mixed $orderId, string $title, string $message): void
    {
        if (! is_string($companyId) || trim($companyId) === '' || ! is_string($orderId) || $orderId === '') {
            return;
        }

        $notifyResult = $this->notifyCompanyClients->execute($companyId, [
            'title' => $title,
            'message' => $message,
            'type' => 'shipment.status',
            'action_kind' => ClientNotificationAction::KIND_REQUEST_DETAILS,
            'action_id' => $orderId,
        ]);

        if (! $notifyResult->success) {
            report(new \RuntimeException('Client request notification failed: '.$notifyResult->message));
        }
    }
}
