<?php

namespace App\Support;

use App\Http\Requests\Client\CreateClientShipmentRequestRequest;
use App\Models\ShipmentOrder;

/**
 * Server-built payload_json snapshot for submit/resubmit (C2.9-A3.4.3b).
 */
final class ShipmentOrderPayloadSnapshotBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function fromOrder(ShipmentOrder $order, int $submissionRevision): array
    {
        $snapshot = [
            'submission_revision' => $submissionRevision,
        ];

        foreach (CreateClientShipmentRequestRequest::allowedFieldNames() as $field) {
            $value = $order->getAttribute($field);

            if ($value === null) {
                continue;
            }

            if (in_array($field, ['requested_pickup_date', 'requested_delivery_date'], true)) {
                $snapshot[$field] = $order->{$field}?->format('Y-m-d');

                continue;
            }

            if (in_array($field, ['cargo_weight', 'cargo_volume', 'pickup_latitude', 'pickup_longitude', 'delivery_latitude', 'delivery_longitude'], true)) {
                $snapshot[$field] = $value !== null ? (float) $value : null;

                continue;
            }

            if (in_array($field, ['hazardous_cargo', 'temperature_controlled'], true)) {
                $snapshot[$field] = (bool) $value;

                continue;
            }

            $snapshot[$field] = $value;
        }

        return $snapshot;
    }
}
