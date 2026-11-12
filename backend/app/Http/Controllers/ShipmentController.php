<?php

namespace App\Http\Controllers;

use App\Actions\Shipment\CaptureShipmentOperationalActualsAction;
use App\Actions\Shipment\ChangeShipmentStatusAction;
use App\Actions\Shipment\CreateShipmentAction;
use App\Actions\Shipment\DeleteShipmentAction;
use App\Actions\Shipment\FinalizeShipmentPriceAction;
use App\Actions\Shipment\GetShipmentAction;
use App\Actions\Shipment\GetShipmentKPIsAction;
use App\Actions\Shipment\GetShipmentQueueAction;
use App\Actions\Shipment\QueryShipmentsAction;
use App\Actions\Shipment\RestoreShipmentAction;
use App\Actions\Shipment\UpdateShipmentAction;
use App\Core\ApiResponse;
use App\Http\Requests\Shipment\CaptureShipmentOperationalActualsRequest;
use App\Http\Requests\Shipment\ChangeShipmentStatusRequest;
use App\Http\Requests\Shipment\CreateShipmentRequest;
use App\Http\Requests\Shipment\QueryShipmentsRequest;
use App\Http\Requests\Shipment\UpdateShipmentRequest;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;

class ShipmentController extends Controller
{
    public function index(QueryShipmentsRequest $request, QueryShipmentsAction $action): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentCollection($result->data),
            $result->message
        );
    }

    public function show(Shipment $shipment, GetShipmentAction $action): JsonResponse
    {
        $this->authorize('view', $shipment);

        $result = $action->execute($shipment->id);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentResource($result->data),
            $result->message
        );
    }

    public function store(CreateShipmentRequest $request, CreateShipmentAction $action): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $result = $action->execute($data);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentResource($result->data),
            $result->message,
            201
        );
    }

    public function update(Shipment $shipment, UpdateShipmentRequest $request, UpdateShipmentAction $action): JsonResponse
    {
        $this->authorize('update', $shipment);

        $result = $action->execute($shipment, $request->validated(), auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentResource($result->data),
            $result->message
        );
    }

    public function changeStatus(Shipment $shipment, ChangeShipmentStatusRequest $request, ChangeShipmentStatusAction $action): JsonResponse
    {
        $this->authorize('changeStatus', $shipment);

        $result = $action->execute(
            $shipment,
            $request->validated()['status_id'],
            auth()->id(),
            $request->validated()['actual_pickup_date'] ?? null,
            $request->validated()['actual_delivery_date'] ?? null
        );

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function captureOperationalActuals(
        Shipment $shipment,
        CaptureShipmentOperationalActualsRequest $request,
        CaptureShipmentOperationalActualsAction $action,
    ): JsonResponse {
        $this->authorize('update', $shipment);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipment, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentOperationalActualsResource($result->data),
            $result->message,
            201
        );
    }

    public function finalizePrice(
        Shipment $shipment,
        FinalizeShipmentPriceAction $action,
    ): JsonResponse {
        $this->authorize('update', $shipment);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $result = $action->execute($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        $result->data->loadMissing('variances');

        return ApiResponse::success(
            new \App\Http\Resources\ShipmentFinalPriceBreakdownResource($result->data),
            $result->message,
            201
        );
    }

    public function destroy(Shipment $shipment, DeleteShipmentAction $action): JsonResponse
    {
        $this->authorize('delete', $shipment);

        $result = $action->execute($shipment, auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function restore(string $shipment, RestoreShipmentAction $action): JsonResponse
    {
        $this->authorize('restore', Shipment::class);

        $result = $action->execute($shipment, auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function queue(GetShipmentQueueAction $action): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function kpis(GetShipmentKPIsAction $action): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $result = $action->execute();

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function deliveryProof(
        string $shipment,
        \Illuminate\Http\Request $request,
        \App\Actions\Shipment\GetShipmentDeliveryProofAction $action
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->executeForAdmin($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function certificate(
        string $shipment,
        \Illuminate\Http\Request $request,
        \App\Actions\Shipment\GenerateDeliveryProofCertificateAction $action
    ): JsonResponse|\Illuminate\Http\Response {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->executeForAdmin($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        /** @var array{pdf_bytes: string, filename: string, hash: string} $data */
        $data = $result->data;

        return response($data['pdf_bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$data['filename'].'"',
            'X-Certificate-Hash' => $data['hash'],
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pod(
        string $shipment,
        \Illuminate\Http\Request $request,
        \App\Support\DriverPodStorage $podStorage
    ): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response {
        $shipmentModel = Shipment::query()->with('deliveryProof')->find($shipment);
        if ($shipmentModel === null || $shipmentModel->deliveryProof === null) {
            return ApiResponse::error('Proof of delivery is not available', 404);
        }

        $proof = $shipmentModel->deliveryProof;
        $type = (string) $request->query('type', 'photo');
        $rawKey = $type === 'signature' ? $proof->signature : $proof->image_url;
        $key = is_string($rawKey) ? str_replace('\\', '/', $rawKey) : '';

        if ($key === '' || ! $podStorage->exists($key)) {
            return ApiResponse::error('Proof of delivery is not available', 404);
        }

        $mime = $podStorage->mimeType($key);
        $downloadName = $podStorage->downloadName($key);

        if ($request->isMethod('HEAD')) {
            return response('', 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return \Illuminate\Support\Facades\Storage::disk(\App\Support\DriverPodStorage::DISK)->response(
            $key,
            $downloadName,
            [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
