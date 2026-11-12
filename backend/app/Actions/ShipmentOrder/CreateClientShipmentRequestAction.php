<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Http\Requests\Client\CreateClientShipmentRequestRequest;
use App\Models\ShipmentOrder;
use App\Models\Status;
use App\Models\User;
use App\Support\ShipmentOrderRequestNumberGenerator;
use App\Support\ShipmentOrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Create a draft Shipment Request for authenticated client users (C2.9-A3.4.2).
 * Not a lifecycle transition — no status history row is written.
 */
class CreateClientShipmentRequestAction
{
    public function execute(User $user, array $validated): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        $draftStatus = Status::query()
            ->where('name', ShipmentOrderStatus::DRAFT)
            ->where('type', ShipmentOrderStatus::TYPE)
            ->first();

        if ($draftStatus === null) {
            return ApiResult::error('Shipment request draft status is not configured', 500);
        }

        $attributes = collect($validated)
            ->only(CreateClientShipmentRequestRequest::allowedFieldNames())
            ->all();

        $order = DB::transaction(function () use ($companyId, $draftStatus, $attributes) {
            return ShipmentOrder::query()->create(array_merge($attributes, [
                'request_number' => ShipmentOrderRequestNumberGenerator::nextNumber(),
                'customer_company_id' => $companyId,
                'submitted_by_user_id' => null,
                'status_id' => $draftStatus->id,
                'submission_revision' => 0,
                'payload_json' => null,
                'converted_shipment_id' => null,
            ]));
        });

        return new ApiResult(
            true,
            $order->fresh(['status']),
            'Shipment request created successfully',
            201
        );
    }
}
