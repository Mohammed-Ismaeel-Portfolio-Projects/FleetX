<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class RestoreShipmentAction
{
    public function execute(string $shipmentId, int $userId): ApiResult
    {
        return DB::transaction(function () use ($shipmentId, $userId) {
            $shipment = Shipment::withTrashed()->find($shipmentId);

            if (!$shipment) {
                return ApiResult::notFound('Shipment not found');
            }

            if (!$shipment->trashed()) {
                return ApiResult::error('Shipment is not deleted', 400);
            }

            $shipment->restore();
            $shipment->update([
                'updated_by' => $userId,
            ]);

            return ApiResult::success($shipment->load(['customerCompany', 'status']), 'Shipment restored successfully');
        });
    }
}
