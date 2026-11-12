<?php

namespace App\Http\Controllers\Client;

use App\Actions\ShipmentOrder\CreateClientShipmentRequestAction;
use App\Actions\ShipmentOrder\GetClientShipmentRequestAction;
use App\Actions\ShipmentOrder\QueryClientShipmentRequestsAction;
use App\Actions\ShipmentOrder\ResubmitClientShipmentRequestAction;
use App\Actions\ShipmentOrder\SubmitClientShipmentRequestAction;
use App\Actions\ShipmentOrder\UpdateClientShipmentRequestAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreateClientShipmentRequestRequest;
use App\Http\Requests\Client\QueryClientShipmentRequestsRequest;
use App\Http\Requests\Client\ResubmitClientShipmentRequestRequest;
use App\Http\Requests\Client\ShowClientShipmentRequestRequest;
use App\Http\Requests\Client\SubmitClientShipmentRequestRequest;
use App\Http\Requests\Client\UpdateClientShipmentRequestRequest;
use App\Http\Resources\Client\ClientShipmentRequestListCollection;
use App\Http\Resources\Client\ClientShipmentRequestShowResource;
use Illuminate\Http\JsonResponse;

/**
 * Client-facing Shipment Request list/show/create/update/submit/resubmit (C2.9-A3.4.x).
 * Distinct from ops ShipmentController and client Shipment read APIs.
 */
class ClientShipmentRequestController extends Controller
{
    public function resubmit(
        ResubmitClientShipmentRequestRequest $request,
        string $shipmentOrder,
        ResubmitClientShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.resubmit');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder);

        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestShowResource($result->data),
            $result->message
        );
    }

    public function submit(
        SubmitClientShipmentRequestRequest $request,
        string $shipmentOrder,
        SubmitClientShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.submit');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder);

        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestShowResource($result->data),
            $result->message
        );
    }

    public function update(
        UpdateClientShipmentRequestRequest $request,
        string $shipmentOrder,
        UpdateClientShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.update');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder, $request->validated());

        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestShowResource($result->data),
            $result->message
        );
    }

    public function store(
        CreateClientShipmentRequestRequest $request,
        CreateClientShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.create');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestShowResource($result->data),
            $result->message,
            $result->statusCode
        );
    }

    public function index(
        QueryClientShipmentRequestsRequest $request,
        QueryClientShipmentRequestsAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.viewAny');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestListCollection($result->data),
            $result->message
        );
    }

    public function show(
        ShowClientShipmentRequestRequest $request,
        string $shipmentOrder,
        GetClientShipmentRequestAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipmentOrder);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentRequestShowResource($result->data),
            $result->message
        );
    }
}
