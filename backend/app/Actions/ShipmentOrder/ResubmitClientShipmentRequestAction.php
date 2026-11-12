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
 * Resubmit a Shipment Request (changes_requested → submitted) for client users (C2.9-A3.4.3c).
 */
class ResubmitClientShipmentRequestAction
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
                    ->with(['status', 'priceBreakdown'])
                    ->whereKey($shipmentOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($order === null) {
                    return ApiResult::notFound('Shipment request not found');
                }

                if ($order->status === null
                    || $order->status->type !== ShipmentOrderStatus::TYPE
                    || $order->status->name !== ShipmentOrderStatus::CHANGES_REQUESTED) {
                    return ApiResult::error(
                        'Shipment request cannot be resubmitted in current status',
                        422
                    );
                }

                $validationErrors = ShipmentOrderSubmissionValidator::validate($order);
                if ($validationErrors !== null) {
                    return ApiResult::validation($validationErrors, 'Submission validation failed');
                }

                $nextRevision = $order->submission_revision + 1;

                $order->update([
                    'submitted_by_user_id' => $user->id,
                    'submission_revision' => $nextRevision,
                    'payload_json' => ShipmentOrderPayloadSnapshotBuilder::fromOrder($order, $nextRevision),
                ]);

                $this->pricingService->calculateAndPersistSnapshot(
                    $order->fresh(['priceBreakdown']),
                    $user,
                    replaceExisting: true,
                );

                $order = $this->transitionAction->execute(
                    $order,
                    ShipmentOrderStatus::SUBMITTED,
                    $user->id
                );

                return ApiResult::success(
                    $order,
                    'Shipment request resubmitted successfully'
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
