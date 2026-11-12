<?php

namespace App\Actions\Shipment;

use App\Core\ApiResult;
use App\Models\Shipment;
use App\Models\User;
use App\Support\DriverPodStorage;
use Illuminate\Support\Str;

/**
 * Company-scoped client POD retrieval (INT-006 + POD-04).
 * Reuses GetClientShipmentAction tenancy: forCustomer + authenticated company_id.
 * Serves the DeliveryProof photo or signature from the private local disk — never public URLs.
 */
class GetClientShipmentPodAction
{
    public function __construct(
        private readonly DriverPodStorage $podStorage
    ) {}

    /**
     * @return ApiResult array{key: string, mime: string, download_name: string}
     */
    public function execute(User $user, string $shipmentId, string $type = 'photo'): ApiResult
    {
        $resolved = $this->resolveOwnedProofKey($user, $shipmentId, $type);
        if (! $resolved->success) {
            return $resolved;
        }

        /** @var string $key */
        $key = $resolved->data;

        return ApiResult::success([
            'key' => $key,
            'mime' => $this->podStorage->mimeType($key),
            'download_name' => $this->podStorage->downloadName($key),
        ], 'Proof of delivery retrieved successfully');
    }

    /**
     * Client-safe availability — no storage keys or DeliveryProof fields.
     *
     * @return ApiResult array{available: bool}
     */
    public function availability(User $user, string $shipmentId): ApiResult
    {
        $resolved = $this->resolveOwnedProofKey($user, $shipmentId, 'photo');
        if (! $resolved->success) {
            if ($resolved->statusCode === 404 && $resolved->message === 'Proof of delivery is not available') {
                return ApiResult::success(['available' => false], 'Proof of delivery is not available');
            }

            return $resolved;
        }

        return ApiResult::success(['available' => true], 'Proof of delivery is available');
    }

    private function resolveOwnedProofKey(User $user, string $shipmentId, string $type): ApiResult
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
            ->with('deliveryProof')
            ->find($shipmentId);

        if ($shipment === null) {
            return ApiResult::notFound('Shipment not found');
        }

        $proof = $shipment->deliveryProof;
        if ($proof === null) {
            return ApiResult::notFound('Proof of delivery is not available');
        }

        $rawKey = $type === 'signature' ? $proof->signature : $proof->image_url;
        $key = is_string($rawKey) ? str_replace('\\', '/', $rawKey) : '';

        if ($key === '' || ! $this->podStorage->exists($key)) {
            return ApiResult::notFound('Proof of delivery is not available');
        }

        return ApiResult::success($key, 'Proof of delivery located');
    }
}
