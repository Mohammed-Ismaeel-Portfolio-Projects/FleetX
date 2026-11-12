<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\Shipment;
use App\Models\Status;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;

class UpdateShipmentAction
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionAction
    ) {}

    public function execute(Shipment $shipment, array $data, int $userId): ApiResult
    {
        return DB::transaction(function () use ($shipment, $data, $userId) {
            $changedData = array_filter($data, function ($value, $key) use ($shipment) {
                return $shipment->$key !== $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($changedData)) {
                return ApiResult::error('No changes detected', 400);
            }

            $statusIdChange = null;
            if (array_key_exists('status_id', $changedData)) {
                $statusIdChange = $changedData['status_id'];
                unset($changedData['status_id']);
            }

            try {
                if ($statusIdChange !== null) {
                    $targetStatus = Status::query()->find($statusIdChange);

                    if ($targetStatus === null || $targetStatus->type !== ShipmentStatus::TYPE) {
                        return ApiResult::error('Target status is not a valid shipment status', 422);
                    }

                    $shipment = $this->transitionAction->execute(
                        $shipment,
                        $targetStatus->name,
                        $userId
                    );
                }

                if (! empty($changedData)) {
                    $changedData['updated_by'] = $userId;
                    $shipment->update($changedData);
                }

                return ApiResult::success(
                    $shipment->load(['customerCompany', 'status']),
                    'Shipment updated successfully'
                );
            } catch (DomainException $e) {
                return ApiResult::error($e->getMessage(), $e->getCode() ?: 422, $e->getContext());
            }
        });
    }
}
