<?php

namespace App\Http\Controllers\Client;

use App\Actions\Shipment\GetClientShipmentAction;
use App\Actions\Shipment\GetClientShipmentPodAction;
use App\Actions\Shipment\GetClientShipmentTrackingAction;
use App\Actions\Shipment\GetClientShipmentVerificationQrAction;
use App\Actions\Shipment\GetShipmentDeliveryProofAction;
use App\Actions\Shipment\QueryClientShipmentsAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\QueryClientShipmentsRequest;
use App\Http\Requests\Client\ShowClientShipmentPodRequest;
use App\Http\Requests\Client\ShowClientShipmentRequest;
use App\Http\Requests\Client\ShowClientShipmentTrackingRequest;
use App\Http\Requests\Client\ShowClientShipmentVerificationQrRequest;
use App\Http\Resources\Client\ClientShipmentListCollection;
use App\Http\Resources\Client\ClientShipmentShowResource;
use App\Http\Resources\Client\ClientShipmentTrackingResource;
use App\Http\Resources\Client\ClientShipmentVerificationQrResource;
use App\Support\DriverPodStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client-facing shipment list/show/tracking (C2.6-C2 / C2.6-C5-D3 / C2.7-C).
 * Distinct from ops ShipmentController — never widens shipment.view / tracking.view.
 */
class ClientShipmentController extends Controller
{
    public function index(
        QueryClientShipmentsRequest $request,
        QueryClientShipmentsAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.viewAny');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentListCollection($result->data),
            $result->message
        );
    }

    public function show(
        ShowClientShipmentRequest $request,
        string $shipment,
        GetClientShipmentAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Scoped retrieval inside action — do not trust route id alone.
        $result = $action->execute($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentShowResource($result->data),
            $result->message
        );
    }

    public function tracking(
        ShowClientShipmentTrackingRequest $request,
        string $shipment,
        GetClientShipmentTrackingAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Scoped retrieval inside action — do not trust route id alone; no ops tracking.
        $result = $action->execute($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new ClientShipmentTrackingResource($result->data),
            $result->message
        );
    }

    public function podStatus(
        ShowClientShipmentPodRequest $request,
        string $shipment,
        GetClientShipmentPodAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->availability($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function deliveryProof(
        ShowClientShipmentPodRequest $request,
        string $shipment,
        GetShipmentDeliveryProofAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->executeForClient($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function certificate(
        ShowClientShipmentPodRequest $request,
        string $shipment,
        \App\Actions\Shipment\GenerateDeliveryProofCertificateAction $action
    ): JsonResponse|Response {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->executeForClient($user, $shipment);

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
        ShowClientShipmentPodRequest $request,
        string $shipment,
        GetClientShipmentPodAction $action
    ): JsonResponse|StreamedResponse|Response {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $type = (string) $request->query('type', 'photo');
        $result = $action->execute($user, $shipment, $type);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        /** @var array{key: string, mime: string, download_name: string} $file */
        $file = $result->data;

        if ($request->isMethod('HEAD')) {
            return response('', 200, [
                'Content-Type' => $file['mime'],
                'Content-Disposition' => 'inline; filename="'.$file['download_name'].'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return Storage::disk(DriverPodStorage::DISK)->response(
            $file['key'],
            $file['download_name'],
            [
                'Content-Type' => $file['mime'],
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function verificationQr(
        ShowClientShipmentVerificationQrRequest $request,
        string $shipment,
        GetClientShipmentVerificationQrAction $action
    ): JsonResponse {
        $this->authorize('client.shipment.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $shipment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data !== null ? new ClientShipmentVerificationQrResource($result->data) : null,
            $result->message
        );
    }
}
