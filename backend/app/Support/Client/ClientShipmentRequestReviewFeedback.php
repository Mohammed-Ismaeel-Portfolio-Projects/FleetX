<?php

namespace App\Support\Client;

use App\Models\ShipmentOrder;
use App\Models\ShipmentOrderStatusHistory;
use App\Support\ShipmentOrderStatus;

/**
 * Client-safe review feedback projection for shipment-request SHOW (C5.3).
 *
 * Source of truth: shipment_order_status_history.reason for the latest history
 * row whose to_status matches the current changes_requested / rejected status.
 * Never reads dispatcher_notes or exposes raw history.
 */
final class ClientShipmentRequestReviewFeedback
{
    /**
     * @return array{message: string}|null
     */
    public static function resolve(ShipmentOrder $order): ?array
    {
        $order->loadMissing('status');

        $statusName = $order->status?->name;
        if ($statusName !== ShipmentOrderStatus::CHANGES_REQUESTED
            && $statusName !== ShipmentOrderStatus::REJECTED) {
            return null;
        }

        if ($order->status_id === null) {
            return null;
        }

        $history = self::latestRelevantHistory($order);
        if ($history === null) {
            return null;
        }

        $message = is_string($history->reason) ? trim($history->reason) : '';
        if ($message === '') {
            return null;
        }

        return ['message' => $message];
    }

    private static function latestRelevantHistory(ShipmentOrder $order): ?ShipmentOrderStatusHistory
    {
        if ($order->relationLoaded('statusHistory')) {
            /** @var ShipmentOrderStatusHistory|null $fromRelation */
            $fromRelation = $order->statusHistory
                ->filter(fn (ShipmentOrderStatusHistory $row) => (int) $row->to_status_id === (int) $order->status_id)
                ->sortBy([
                    ['created_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first();

            return $fromRelation;
        }

        return ShipmentOrderStatusHistory::query()
            ->where('shipment_order_id', $order->id)
            ->where('to_status_id', $order->status_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
