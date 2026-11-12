<?php

namespace App\Actions\ShipmentOrder;

use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentOrderTransitionException;
use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Models\Status;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentOrderTransitionMap;
use Illuminate\Support\Facades\DB;

/**
 * Canonical writer for ShipmentOrder lifecycle status changes (C2.9-A3.2).
 *
 * Future API/workflow code must use this action — not direct status_id mutation.
 */
class TransitionShipmentOrderStatusAction
{
    public function execute(
        ShipmentOrder $shipmentOrder,
        string $toStatusName,
        ?int $changedByUserId = null,
        ?string $reason = null
    ): ShipmentOrder {
        return DB::transaction(function () use ($shipmentOrder, $toStatusName, $changedByUserId, $reason) {
            $locked = ShipmentOrder::query()
                ->whereKey($shipmentOrder->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('status');

            if ($locked->status === null || $locked->status->type !== ShipmentOrderStatus::TYPE) {
                throw new DomainException(
                    'ShipmentOrder status is not a shipment_order lifecycle status',
                    422,
                    ['status_id' => $locked->status_id]
                );
            }

            $fromStatusName = $locked->status->name;
            $fromStatusId = $locked->status_id;

            $toStatus = Status::query()
                ->where('name', $toStatusName)
                ->where('type', ShipmentOrderStatus::TYPE)
                ->first();

            if ($toStatus === null) {
                throw new DomainException(
                    'Target status is not a valid shipment_order status',
                    422,
                    ['to' => $toStatusName]
                );
            }

            if (! ShipmentOrderTransitionMap::canTransition($fromStatusName, $toStatusName)) {
                throw new InvalidShipmentOrderTransitionException($fromStatusName, $toStatusName);
            }

            ShipmentOrderStatusHistory::query()->create([
                'shipment_order_id' => $locked->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatus->id,
                'changed_by_user_id' => $changedByUserId,
                'reason' => $reason,
            ]);

            $locked->update(['status_id' => $toStatus->id]);

            return $locked->fresh(['status', 'statusHistory.fromStatus', 'statusHistory.toStatus', 'statusHistory.changedBy']);
        });
    }
}
