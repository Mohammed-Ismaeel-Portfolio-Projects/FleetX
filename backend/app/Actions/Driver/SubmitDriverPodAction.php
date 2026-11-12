<?php

namespace App\Actions\Driver;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\DeliveryProof;
use App\Models\User;
use App\Support\DriverAssignmentTransitionMap;
use App\Support\DriverExecutionPhase;
use App\Support\DriverPodStorage;
use App\Support\ShipmentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Driver POD submit (D7 + POD-02) — server-bound shipment ownership & complete delivery evidence.
 */
class SubmitDriverPodAction
{
    public function __construct(
        private readonly DriverPodStorage $podStorage
    ) {}

    /**
     * @param  array{receiver_name?: string|null, receiver_role?: string|null, delivery_notes?: string|null, condition_status?: string|null, latitude?: float|int|string|null, longitude?: float|int|string|null}  $metadata
     */
    public function execute(
        User $user,
        int|string $assignmentId,
        UploadedFile $photo,
        UploadedFile $signature,
        array $metadata = []
    ): ApiResult {
        $user->loadMissing(['role', 'driver']);

        if ($user->role?->name !== 'driver') {
            return ApiResult::error('Forbidden', 403);
        }

        $driver = $user->driver;
        if ($driver === null) {
            return ApiResult::error('Driver account is not linked.', 403);
        }

        try {
            return DB::transaction(function () use ($user, $driver, $assignmentId, $photo, $signature, $metadata) {
                /** @var Assignment|null $assignment */
                $assignment = Assignment::query()
                    ->forDriver($driver->id)
                    ->lockForUpdate()
                    ->find($assignmentId);

                if ($assignment === null) {
                    return ApiResult::notFound('Assignment not found');
                }

                $assignment->loadMissing(['status', 'shipment.status', 'shipment.deliveryProof']);
                $statusName = $assignment->status?->name;

                if (! $assignment->is_active || $statusName !== DriverAssignmentTransitionMap::IN_OPERATION) {
                    return ApiResult::error('Assignment is not in operation.', 409, [
                        'code' => 'ASSIGNMENT_NOT_IN_OPERATION',
                    ]);
                }

                $phase = $assignment->execution_phase;
                if (! DriverExecutionPhase::isPodEligible($phase)) {
                    return ApiResult::error('Delivery must be verified before submitting proof.', 409, [
                        'code' => 'DELIVERY_NOT_VERIFIED',
                    ]);
                }

                $shipment = $assignment->shipment;
                if ($shipment === null) {
                    return ApiResult::error('Assignment has no shipment.', 422);
                }

                $shipmentStatus = $shipment->status?->name;
                if ($shipmentStatus !== ShipmentStatus::DELIVERED && $shipmentStatus !== ShipmentStatus::COMPLETED) {
                    return ApiResult::error('Shipment must be Delivered before proof of delivery.', 409, [
                        'code' => 'SHIPMENT_NOT_DELIVERED',
                    ]);
                }

                $existing = DeliveryProof::query()
                    ->where('shipment_id', $shipment->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($assignment->execution_phase !== DriverExecutionPhase::POD_SUBMITTED) {
                        $assignment->update([
                            'execution_phase' => DriverExecutionPhase::POD_SUBMITTED,
                            'updated_by' => $user->id,
                        ]);
                    }
                    $assignment->load(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer']);

                    return ApiResult::success([
                        'assignment' => $assignment,
                        'pod' => $this->podDto($existing),
                    ], 'Proof of delivery already submitted');
                }

                $photoKey = $this->podStorage->storePhoto((string) $shipment->id, $photo);
                $signatureKey = $this->podStorage->storeSignature((string) $shipment->id, $signature);

                $receiverName = isset($metadata['receiver_name']) && is_string($metadata['receiver_name']) && trim($metadata['receiver_name']) !== ''
                    ? trim($metadata['receiver_name'])
                    : 'Receiver';
                $receiverRole = isset($metadata['receiver_role']) && is_string($metadata['receiver_role']) && trim($metadata['receiver_role']) !== ''
                    ? trim($metadata['receiver_role'])
                    : null;
                $deliveryNotes = isset($metadata['delivery_notes']) && is_string($metadata['delivery_notes']) && trim($metadata['delivery_notes']) !== ''
                    ? trim($metadata['delivery_notes'])
                    : null;
                $conditionStatus = isset($metadata['condition_status']) && is_string($metadata['condition_status']) && trim($metadata['condition_status']) !== ''
                    ? trim($metadata['condition_status'])
                    : 'good';
                $latitude = isset($metadata['latitude']) && is_numeric($metadata['latitude']) ? (float) $metadata['latitude'] : null;
                $longitude = isset($metadata['longitude']) && is_numeric($metadata['longitude']) ? (float) $metadata['longitude'] : null;

                $proof = DeliveryProof::query()->create([
                    'shipment_id' => $shipment->id,
                    'assignment_id' => $assignment->id,
                    'driver_id' => $driver->id,
                    'receiver_name' => $receiverName,
                    'receiver_role' => $receiverRole,
                    'delivery_notes' => $deliveryNotes,
                    'condition_status' => $conditionStatus,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'image_url' => $photoKey,
                    'signature' => $signatureKey,
                    'confirmed_at' => now(),
                ]);

                $assignment->update([
                    'execution_phase' => DriverExecutionPhase::POD_SUBMITTED,
                    'updated_by' => $user->id,
                ]);

                $assignment->load(['status', 'shipment.status', 'shipment.deliveryProof', 'vehicle', 'trailer']);

                return ApiResult::success([
                    'assignment' => $assignment,
                    'pod' => $this->podDto($proof),
                ], 'Proof of delivery submitted');
            });
        } catch (InvalidArgumentException $e) {
            return ApiResult::error($e->getMessage(), 422, [
                'code' => 'POD_UPLOAD_INVALID',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function podDto(DeliveryProof $proof): array
    {
        return [
            'submitted' => true,
            'confirmed_at' => $proof->confirmed_at?->format('Y-m-d H:i:s'),
            'receiver_name' => $proof->receiver_name,
            'receiver_role' => $proof->receiver_role,
            'condition_status' => $proof->condition_status,
            'delivery_notes' => $proof->delivery_notes,
            'latitude' => $proof->latitude !== null ? (float) $proof->latitude : null,
            'longitude' => $proof->longitude !== null ? (float) $proof->longitude : null,
            'assignment_id' => $proof->assignment_id,
            'driver_id' => $proof->driver_id,
        ];
    }
}
