<?php

namespace App\Actions\ShipmentOrder;

use App\Actions\Notification\NotifyCompanyClientUsersAction;
use App\Actions\Shipment\CreateShipmentAction;
use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Convert an approved ShipmentOrder into one operational Shipment (C2.9-B3.3).
 *
 * Authorization is enforced by callers (policy/gate). No HTTP layer in this phase.
 */
class ConvertShipmentOrderToShipmentAction
{
    public function __construct(
        private readonly CreateShipmentAction $createShipmentAction,
        private readonly TransitionShipmentOrderStatusAction $transitionAction,
        private readonly NotifyCompanyClientUsersAction $notifyCompanyClients
    ) {}

    public function execute(User $user, string $shipmentOrderId): ApiResult
    {
        $notifyCompanyId = null;
        $notifyOrderId = null;

        try {
            $result = DB::transaction(function () use ($user, $shipmentOrderId, &$notifyCompanyId, &$notifyOrderId) {
                $order = ShipmentOrder::query()
                    ->with(['status'])
                    ->whereKey($shipmentOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ApiResult::notFound('Shipment request not found');
                }

                if ($this->isAlreadyConverted($order)) {
                    return ApiResult::success(
                        $this->buildResultPayload($order),
                        'Shipment request already converted'
                    );
                }

                if (! $this->canConvert($order)) {
                    return ApiResult::error(
                        'Historical shipment requests are immutable and cannot be modified.',
                        422
                    );
                }

                $createResult = $this->createShipmentAction->execute(
                    ShipmentOrderConversionMapper::toShipmentAttributes($order, $user->id)
                );

                if (! $createResult->success) {
                    return $createResult;
                }

                /** @var Shipment $shipment */
                $shipment = $createResult->data;

                $order->update(['converted_shipment_id' => $shipment->id]);
                $shipment->update(['shipment_order_id' => $order->id]);

                $this->transitionAction->execute(
                    $order->fresh(['status']),
                    ShipmentOrderStatus::CONVERTED,
                    $user->id
                );

                $notifyCompanyId = $order->customer_company_id;
                $notifyOrderId = $order->id;

                return ApiResult::success(
                    $this->buildResultPayload($order->fresh(['status', 'convertedShipment', 'linkedShipment'])),
                    'Shipment request converted successfully'
                );
            });
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() ?: 422);
        }

        if ($result->success && $notifyOrderId !== null) {
            $this->notifyRequestClients(
                $notifyCompanyId,
                $notifyOrderId,
                'Shipment request converted',
                'Your shipment request has been converted into a shipment.'
            );
        }

        return $result;
    }

    private function isAlreadyConverted(ShipmentOrder $order): bool
    {
        return $order->status !== null
            && $order->status->type === ShipmentOrderStatus::TYPE
            && $order->status->name === ShipmentOrderStatus::CONVERTED
            && $order->converted_shipment_id !== null;
    }

    private function canConvert(ShipmentOrder $order): bool
    {
        return $order->status !== null
            && $order->status->type === ShipmentOrderStatus::TYPE
            && $order->status->name === ShipmentOrderStatus::APPROVED
            && $order->converted_shipment_id === null;
    }

    /**
     * @return array{shipment_order: ShipmentOrder, shipment: Shipment}
     */
    private function buildResultPayload(ShipmentOrder $order): array
    {
        $order->loadMissing(['status', 'convertedShipment.status', 'linkedShipment.status']);

        /** @var Shipment $shipment */
        $shipment = $order->convertedShipment ?? $order->linkedShipment;

        return [
            'shipment_order' => $order,
            'shipment' => $shipment,
        ];
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
