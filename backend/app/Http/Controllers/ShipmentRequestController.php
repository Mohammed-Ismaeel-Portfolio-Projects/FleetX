<?php

namespace App\Http\Controllers;

use App\Actions\ShipmentOrder\ApproveShipmentOrderAction;
use App\Actions\ShipmentOrder\ConvertShipmentOrderToShipmentAction;
use App\Actions\ShipmentOrder\GetShipmentRequestAction;
use App\Actions\ShipmentOrder\QueryShipmentRequestsAction;
use App\Actions\ShipmentOrder\RejectShipmentOrderAction;
use App\Actions\ShipmentOrder\RequestChangesShipmentOrderAction;
use App\Actions\ShipmentOrder\StartReviewShipmentOrderAction;
use App\Core\ApiResponse;
use App\Http\Requests\ShipmentOrder\ApproveShipmentOrderRequest;
use App\Http\Requests\ShipmentOrder\ConvertShipmentOrderRequest;
use App\Http\Requests\ShipmentOrder\QueryShipmentRequestsRequest;
use App\Http\Requests\ShipmentOrder\RejectShipmentOrderRequest;
use App\Http\Requests\ShipmentOrder\RequestChangesShipmentOrderRequest;
use App\Http\Requests\ShipmentOrder\ShowShipmentRequestRequest;
use App\Http\Requests\ShipmentOrder\StartReviewShipmentOrderRequest;
use App\Http\Resources\ShipmentOrder\ShipmentRequestListCollection;
use App\Http\Resources\ShipmentOrder\ShipmentRequestShowResource;
use App\Models\ShipmentOrder;
use Illuminate\Http\JsonResponse;

/**
 * Ops Shipment Request API for Dispatcher/Admin (C2.9-B2.1 read, C2.9-B2.2 review, C2.9-B3.4 convert).
 * Distinct from Client\ClientShipmentRequestController.
 */
class ShipmentRequestController extends Controller
{
    public function startReview(
        StartReviewShipmentOrderRequest $request,
        ShipmentOrder $shipmentOrder,
        StartReviewShipmentOrderAction $action
    ): JsonResponse {
        $this->authorize('startReview', $shipmentOrder);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder->id);

        return $this->transitionResponse($result);
    }

    public function requestChanges(
        RequestChangesShipmentOrderRequest $request,
        ShipmentOrder $shipmentOrder,
        RequestChangesShipmentOrderAction $action
    ): JsonResponse {
        $this->authorize('requestChanges', $shipmentOrder);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder->id, $request->validated('reason'));

        return $this->transitionResponse($result);
    }

    public function approve(
        ApproveShipmentOrderRequest $request,
        ShipmentOrder $shipmentOrder,
        ApproveShipmentOrderAction $action
    ): JsonResponse {
        $this->authorize('approve', $shipmentOrder);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder->id, $request->validated('reason'));

        return $this->transitionResponse($result);
    }

    public function reject(
        RejectShipmentOrderRequest $request,
        ShipmentOrder $shipmentOrder,
        RejectShipmentOrderAction $action
    ): JsonResponse {
        $this->authorize('reject', $shipmentOrder);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder->id, $request->validated('reason'));

        return $this->transitionResponse($result);
    }

    public function convert(
        ConvertShipmentOrderRequest $request,
        ShipmentOrder $shipmentOrder,
        ConvertShipmentOrderToShipmentAction $action,
        GetShipmentRequestAction $getAction
    ): JsonResponse {
        $this->authorize('convert', $shipmentOrder);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder->id);

        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        $showResult = $getAction->execute($shipmentOrder->id);

        if (! $showResult->success) {
            return ApiResponse::error($showResult->message, $showResult->statusCode);
        }

        $statusCode = $result->message === 'Shipment request already converted' ? 200 : 201;

        return ApiResponse::success(
            new ShipmentRequestShowResource($showResult->data),
            $result->message,
            $statusCode
        );
    }

    public function index(
        QueryShipmentRequestsRequest $request,
        QueryShipmentRequestsAction $action
    ): JsonResponse {
        $this->authorize('viewAny', ShipmentOrder::class);

        $result = $action->execute($request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ShipmentRequestListCollection($result->data),
            $result->message
        );
    }

    public function show(
        ShowShipmentRequestRequest $request,
        ShipmentOrder $shipmentOrder,
        GetShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('view', $shipmentOrder);

        $result = $action->execute($shipmentOrder->id);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ShipmentRequestShowResource($result->data),
            $result->message
        );
    }

    private function transitionResponse(\App\Core\ApiResult $result): JsonResponse
    {
        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ShipmentRequestShowResource($result->data),
            $result->message
        );
    }
}
