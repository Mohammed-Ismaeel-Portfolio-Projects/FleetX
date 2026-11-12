<?php

namespace App\Actions\ShipmentOrder;

use App\Core\ApiResult;
use App\Exceptions\DomainException;
use App\Models\ShipmentOrder;
use App\Models\User;
use App\Services\Pricing\Exceptions\ShipmentOrderPricingException;
use App\Services\Pricing\ShipmentOrderPricingService;
use App\Support\ShipmentOrderPayloadSnapshotBuilder;
use App\Support\ShipmentOrderStatus;
use App\Support\ShipmentOrderSubmissionValidator;
use Illuminate\Support\Facades\DB;

/**
 * Submit a draft Shipment Request (draft → submitted) for client users (C2.9-A3.4.3b).
 */
class SubmitClientShipmentRequestAction
{
    public function __construct(
        private readonly TransitionShipmentOrderStatusAction $transitionAction,
        private readonly ShipmentOrderPricingService $pricingService,
    ) {}

    public function execute(User $user, string $shipmentOrderId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        try {
            return DB::transaction(function () use ($user, $companyId, $shipmentOrderId) {
                $order = ShipmentOrder::query()
                    ->forCustomer($companyId)
                    ->with(['status'])
                    ->whereKey($shipmentOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ApiResult::notFound('Shipment request not found');
                }

                if ($order->status === null
                    || $order->status->type !== ShipmentOrderStatus::TYPE
                    || $order->status->name !== ShipmentOrderStatus::DRAFT) {
                    return ApiResult::error(
                        'Shipment request cannot be submitted in current status',
                        422
                    );
                }

                $validationErrors = ShipmentOrderSubmissionValidator::validate($order);
                if ($validationErrors !== null) {
                    return ApiResult::validation($validationErrors, 'Submission validation failed');
                }

                $order->update([
                    'submitted_by_user_id' => $user->id,
                    'submission_revision' => 1,
                    'payload_json' => ShipmentOrderPayloadSnapshotBuilder::fromOrder($order, 1),
                ]);

                $this->pricingService->calculateAndPersistSnapshot($order->fresh(), $user);

                $order = $this->transitionAction->execute(
                    $order,
                    ShipmentOrderStatus::SUBMITTED,
                    $user->id
                );

                return ApiResult::success(
                    $order,
                    'Shipment request submitted successfully'
                );
            });
        } catch (DomainException $e) {
            return ApiResult::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (ShipmentOrderPricingException $e) {
            if ($e->statusCode === 422) {
                return ApiResult::error($e->getMessage(), 422);
            }

            return ApiResult::error($e->getMessage(), $e->statusCode);
        }
    }
}
