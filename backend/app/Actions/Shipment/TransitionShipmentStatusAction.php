<?php

namespace App\Actions\Shipment;

use App\Actions\Notification\NotifyCompanyClientUsersAction;
use App\Exceptions\DomainException;
use App\Exceptions\InvalidShipmentTransitionException;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Status;
use App\Support\ClientNotificationAction;
use App\Support\ShipmentStatus;
use App\Support\ShipmentTransitionMap;
use Illuminate\Support\Facades\DB;

/**
 * Canonical writer for Shipment operational status changes (C2.9-C1.4).
 *
 * Future workflow code must use this action — not direct status_id mutation.
 * Client inbox fan-out happens only after a successful mutation (C4.2-A2.3).
 */
class TransitionShipmentStatusAction
{
    public function __construct(
        private readonly NotifyCompanyClientUsersAction $notifyCompanyClients
    ) {}

    public function execute(
        Shipment $shipment,
        string $toStatusName,
        ?int $changedByUserId = null,
        ?string $actualPickupDate = null,
        ?string $actualDeliveryDate = null,
        bool $notifyClients = true,
        ?string $actualPickupAt = null,
        ?string $actualDeliveryAt = null
    ): Shipment {
        $updated = DB::transaction(function () use ($shipment, $toStatusName, $changedByUserId, $actualPickupDate, $actualDeliveryDate, $actualPickupAt, $actualDeliveryAt) {
            $locked = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('status');

            if ($locked->status === null || $locked->status->type !== ShipmentStatus::TYPE) {
                throw new DomainException(
                    'Shipment status is not a shipment lifecycle status',
                    422,
                    ['status_id' => $locked->status_id]
                );
            }

            $fromStatusName = $locked->status->name;
            $fromStatusId = $locked->status_id;

            $toStatus = Status::query()
                ->where('name', $toStatusName)
                ->where('type', ShipmentStatus::TYPE)
                ->first();

            if ($toStatus === null) {
                throw new DomainException(
                    'Target status is not a valid shipment status',
                    422,
                    ['to' => $toStatusName]
                );
            }

            if (! ShipmentTransitionMap::canTransition($fromStatusName, $toStatusName)) {
                throw new InvalidShipmentTransitionException($fromStatusName, $toStatusName);
            }

            ShipmentStatusHistory::query()->create([
                'shipment_id' => $locked->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatus->id,
                'changed_by_user_id' => $changedByUserId,
            ]);

            $updateData = [
                'status_id' => $toStatus->id,
                'updated_by' => $changedByUserId,
            ];

            if ($actualPickupDate !== null) {
                $updateData['actual_pickup_date'] = $actualPickupDate;
            }

            if ($actualDeliveryDate !== null) {
                $updateData['actual_delivery_date'] = $actualDeliveryDate;
            }

            if ($locked->actual_pickup_at === null) {
                if ($actualPickupAt !== null) {
                    $updateData['actual_pickup_at'] = $actualPickupAt;
                } elseif ($actualPickupDate !== null || $toStatusName === ShipmentStatus::IN_TRANSIT) {
                    $updateData['actual_pickup_at'] = now();
                }
            }

            if ($locked->actual_delivery_at === null) {
                if ($actualDeliveryAt !== null) {
                    $updateData['actual_delivery_at'] = $actualDeliveryAt;
                } elseif ($actualDeliveryDate !== null || in_array($toStatusName, [ShipmentStatus::DELIVERED, ShipmentStatus::COMPLETED], true)) {
                    $updateData['actual_delivery_at'] = now();
                }
            }

            $locked->update($updateData);

            return $locked->fresh([
                'customerCompany',
                'status',
                'statusHistory.fromStatus',
                'statusHistory.toStatus',
                'statusHistory.changedBy',
            ]);
        });

        if ($notifyClients) {
            $this->notifyClientOfSuccessfulTransition($updated, $toStatusName);
        }

        return $updated;
    }

    /**
     * Public wrapper for deferred client notify after an outer transaction commits (D5).
     */
    public function notifyClientOfSuccessfulTransitionPublic(Shipment $shipment, string $toStatusName): void
    {
        $this->notifyClientOfSuccessfulTransition($shipment, $toStatusName);
    }

    /**
     * Customer-visible transitions only. Unmapped canonical statuses emit nothing.
     *
     * @return array{title: string, message: string, action_kind: string}|null
     */
    private function clientCopyForStatus(string $toStatusName): ?array
    {
        return match ($toStatusName) {
            ShipmentStatus::ASSIGNED => [
                'title' => 'Shipment assigned',
                'message' => 'Your shipment has been assigned and is progressing toward fulfillment.',
                'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            ],
            ShipmentStatus::IN_TRANSIT => [
                'title' => 'Shipment in transit',
                'message' => 'Your shipment is now in transit.',
                'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            ],
            ShipmentStatus::DELIVERED => [
                'title' => 'Shipment delivered',
                'message' => 'Your shipment has been delivered.',
                'action_kind' => ClientNotificationAction::KIND_SHIPMENT_PROGRESS,
            ],
            ShipmentStatus::COMPLETED => [
                'title' => 'Shipment completed',
                'message' => 'Your shipment has been completed.',
                'action_kind' => ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            ],
            default => null,
        };
    }

    private function notifyClientOfSuccessfulTransition(Shipment $shipment, string $toStatusName): void
    {
        if ($shipment->status === null || $shipment->status->name !== $toStatusName) {
            return;
        }

        $copy = $this->clientCopyForStatus($toStatusName);
        if ($copy === null) {
            return;
        }

        $companyId = $shipment->customer_company_id;
        $shipmentId = $shipment->id;

        if (! is_string($companyId) || trim($companyId) === '' || ! is_string($shipmentId) || $shipmentId === '') {
            return;
        }

        $notifyResult = $this->notifyCompanyClients->execute($companyId, [
            'title' => $copy['title'],
            'message' => $copy['message'],
            'type' => 'shipment.status',
            'action_kind' => $copy['action_kind'],
            'action_id' => $shipmentId,
        ]);

        if (! $notifyResult->success) {
            report(new \RuntimeException('Client shipment notification failed: '.$notifyResult->message));
        }
    }
}
