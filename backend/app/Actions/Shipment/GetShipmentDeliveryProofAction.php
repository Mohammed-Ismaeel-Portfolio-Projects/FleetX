<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\User;
use App\Support\DriverPodStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GetShipmentDeliveryProofAction
{
    public function __construct(
        private readonly DriverPodStorage $podStorage
    ) {}

    public function executeForClient(User $user, string $shipmentId): ApiResult
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            return ApiResult::error('Forbidden', 403);
        }

        if (! Str::isUuid($shipmentId)) {
            return ApiResult::notFound('Shipment not found');
        }

        $shipment = Shipment::query()
            ->forCustomer($companyId)
            ->with(['deliveryProof.driver.user', 'assignments.driver.user'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        return $this->formatResult($shipment, true);
    }

    public function executeForAdmin(User $user, string $shipmentId): ApiResult
    {
        if (! Str::isUuid($shipmentId)) {
            return ApiResult::notFound('Shipment not found');
        }

        $shipment = Shipment::query()
            ->with(['deliveryProof.driver.user', 'assignments.driver.user'])
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        return $this->formatResult($shipment, false);
    }

    private function formatResult(Shipment $shipment, bool $isClient): ApiResult
    {
        $proof = $shipment->deliveryProof;
        if ($proof === null) {
            return ApiResult::notFound('Delivery evidence is not available yet');
        }

        $photoKey = is_string($proof->image_url) ? str_replace('\\', '/', $proof->image_url) : '';
        $photoAvailable = $photoKey !== '' && $this->podStorage->exists($photoKey);

        $sigKey = is_string($proof->signature) ? str_replace('\\', '/', $proof->signature) : '';
        $sigAvailable = $sigKey !== '' && $this->podStorage->exists($sigKey);

        $strokes = null;
        if ($sigAvailable && str_ends_with($sigKey, '.json')) {
            try {
                $raw = Storage::disk(DriverPodStorage::DISK)->get($sigKey);
                if (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $strokes = $decoded['strokes'] ?? $decoded;
                    }
                }
            } catch (\Throwable) {
                $strokes = null;
            }
        }

        // Driver details
        $driverObj = null;
        $driver = $proof->driver;
        if ($driver === null && $proof->assignment_id) {
            $assignment = $shipment->assignments->firstWhere('id', $proof->assignment_id);
            $driver = $assignment?->driver;
        }

        if ($driver !== null) {
            $driverObj = [
                'id' => $driver->id,
                'name' => $driver->user?->name ?? 'Driver #'.$driver->id,
            ];
        }

        $photoEndpoint = $isClient
            ? "/api/v1/client/shipments/{$shipment->id}/pod"
            : "/api/v1/shipments/{$shipment->id}/pod";

        $sigEndpoint = $isClient
            ? "/api/v1/client/shipments/{$shipment->id}/pod?type=signature"
            : "/api/v1/shipments/{$shipment->id}/pod?type=signature";

        return ApiResult::success([
            'shipment_id' => $shipment->id,
            'receiver' => [
                'name' => $proof->receiver_name,
                'role' => $proof->receiver_role,
            ],
            'condition' => [
                'status' => $proof->condition_status ?? 'good',
            ],
            'notes' => $proof->delivery_notes,
            'location' => [
                'latitude' => $proof->latitude !== null ? (float) $proof->latitude : null,
                'longitude' => $proof->longitude !== null ? (float) $proof->longitude : null,
            ],
            'delivered_at' => $proof->confirmed_at?->toIso8601String() ?? $proof->confirmed_at,
            'driver' => $driverObj,
            'photo' => [
                'available' => $photoAvailable,
                'url' => $photoAvailable ? $photoEndpoint : null,
            ],
            'signature' => [
                'available' => $sigAvailable,
                'url' => $sigAvailable ? $sigEndpoint : null,
                'strokes' => $strokes,
            ],
        ], 'Delivery evidence retrieved successfully');
    }
}
