<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Http\Requests\Client\CreateClientShipmentRequestRequest;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Support\ShipmentOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Update a client Shipment Request while editable (C2.9-A3.4.3a draft, C2.9-B2.3 changes_requested).
 * Not a lifecycle transition — no status history row is written.
 */
class UpdateClientShipmentRequestAction
{
    /**
     * @var list<string>
     */
    private const EDITABLE_STATUSES = [
        ShipmentOrderStatus::DRAFT,
        ShipmentOrderStatus::CHANGES_REQUESTED,
    ];

    public function execute(User $user, string $shipmentOrderId, array $validated): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $attributes = collect($validated)
            ->only(CreateClientShipmentRequestRequest::allowedFieldNames())
            ->all();

        if ($attributes === []) {
            return ApiResult::validation(
                ['body' => ['At least one writable field must be supplied.']],
                'Validation failed'
            );
        }

        return DB::transaction(function () use ($companyId, $shipmentOrderId, $attributes) {
            $order = ShipmentOrder::query()
                ->forCustomer($companyId)
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
                || ! in_array($order->status->name, self::EDITABLE_STATUSES, true)) {
                return ApiResult::error(
                    'Shipment request cannot be updated in current status',
                    422
                );
            }

            $order->update($attributes);

            return ApiResult::success(
                $order->fresh(['status']),
                'Shipment request updated successfully'
            );
        });
    }
}
