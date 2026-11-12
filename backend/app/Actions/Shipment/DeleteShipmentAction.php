<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class DeleteShipmentAction
{
    public function execute(Shipment $shipment, int $userId): ApiResult
    {
        return DB::transaction(function () use ($shipment, $userId) {
            $shipment->update([
                'updated_by' => $userId,
            ]);

            $shipment->delete();

            return ApiResult::success(null, 'Shipment deleted successfully');
        });
    }
}
