<?php

namespace App\Actions\Assignment;

use App\Actions\Shipment\TransitionShipmentStatusAction;
use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\Trailer;
use App\Models\Status;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use App\Support\ShipmentStatus;
use Illuminate\Support\Facades\DB;

class CreateAssignmentAction
{
    public function __construct(
        private readonly TransitionShipmentStatusAction $transitionShipmentStatusAction
    ) {}

    public function execute(array $data): ApiResult
    {
        $result = DB::transaction(function () use ($data) {
            $shipment = null;
            if (!empty($data['shipment_id'])) {
                $shipment = Shipment::whereKey($data['shipment_id'])->lockForUpdate()->first();
                if (!$shipment) {
                    return ApiResult::notFound('Shipment not found');
                }

                $existingShipmentAssignment = Assignment::where('shipment_id', $data['shipment_id'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingShipmentAssignment) {
                    return ApiResult::error('Shipment already has an active assignment', 422);
                }
            }

            $isPartnerExecuted = $shipment !== null && $shipment->isPartnerExecuted();
            $executingCompanyId = $shipment?->executing_company_id;

            // Resolve and validate Vehicle
            if (!empty($data['vehicle_id'])) {
                $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->first();

                if (!$vehicle) {
                    return ApiResult::notFound('Vehicle not found');
                }

                if ($isPartnerExecuted) {
                    if (!$vehicle->isPartnerFleet() || $vehicle->company_id !== $executingCompanyId) {
                        return ApiResult::error('Outbound partner shipments must be assigned to partner vehicles belonging to the executing partner company', 422);
                    }
                } else {
                    if ($vehicle->isPartnerFleet()) {
                        return ApiResult::error('Internally executed shipments must be assigned to FleetX internal fleet vehicles', 422);
                    }

                    if ($vehicle->driver_id) {
                        $resolvedDriverId = $vehicle->driver_id;
                        if (!empty($data['driver_id']) && (int)$data['driver_id'] !== (int)$resolvedDriverId) {
                            return ApiResult::error('Submitted driver does not match the permanent driver paired with this vehicle', 422);
                        }
                        $data['driver_id'] = $resolvedDriverId;
                    } else if (!empty($data['driver_id'])) {
                        $resolvedDriverId = $data['driver_id'];
                    } else {
                        return ApiResult::error('Selected vehicle has no assigned driver. An administrator must pair a driver before this vehicle can be assigned.', 422);
                    }
                }

                $existingVehicleAssignment = Assignment::where('vehicle_id', $data['vehicle_id'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingVehicleAssignment) {
                    return ApiResult::error('Vehicle is already assigned to an active assignment', 422);
                }
            }

            // Resolve and validate Driver
            if (!empty($data['driver_id'])) {
                $driver = Driver::whereKey($data['driver_id'])->lockForUpdate()->first();
                if (!$driver) {
                    return ApiResult::notFound('Paired driver not found');
                }

                if ($isPartnerExecuted) {
                    if (!$driver->isPartnerFleet() || $driver->company_id !== $executingCompanyId) {
                        return ApiResult::error('Outbound partner shipments must be assigned to partner drivers belonging to the executing partner company', 422);
                    }
                } else {
                    if ($driver->isPartnerFleet()) {
                        return ApiResult::error('Internally executed shipments must be assigned to FleetX internal fleet drivers', 422);
                    }
                }

                $existingDriverAssignment = Assignment::where('driver_id', $data['driver_id'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingDriverAssignment) {
                    return ApiResult::error('Driver is already assigned to an active assignment', 422);
                }
            }

            // Resolve and validate Trailer
            if (!empty($data['trailer_id'])) {
                $trailer = Trailer::whereKey($data['trailer_id'])->lockForUpdate()->first();
                if (!$trailer) {
                    return ApiResult::notFound('Trailer not found');
                }

                if ($isPartnerExecuted) {
                    if (!$trailer->isPartnerFleet() || $trailer->company_id !== $executingCompanyId) {
                        return ApiResult::error('Outbound partner shipments must be assigned to partner trailers belonging to the executing partner company', 422);
                    }
                } else {
                    if ($trailer->isPartnerFleet()) {
                        return ApiResult::error('Internally executed shipments must be assigned to FleetX internal fleet trailers', 422);
                    }
                }

                $existingTrailerAssignment = Assignment::where('trailer_id', $data['trailer_id'])
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingTrailerAssignment) {
                    return ApiResult::error('Trailer is already assigned to an active assignment', 422);
                }
            }

            // Create assignment
            $assignment = Assignment::create([
                'shipment_id' => $data['shipment_id'] ?? null,
                'assignment_code' => $data['assignment_code'],
                'driver_id' => $data['driver_id'] ?? null,
                'vehicle_id' => $data['vehicle_id'] ?? null,
                'trailer_id' => $data['trailer_id'] ?? null,
                'assigned_by' => $data['assigned_by'],
                'status_id' => $data['status_id'],
                'assigned_at' => $data['assigned_at'],
                'ended_at' => $data['ended_at'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['assigned_by'],
                'updated_by' => $data['assigned_by'],
            ]);

            // Update driver status to Assigned if assignment is active
            if (!empty($data['driver_id']) && $assignment->is_active) {
                $assignedStatus = Status::where('name', 'Assigned')->where('type', 'driver')->first();
                if ($assignedStatus) {
                    Driver::where('id', $data['driver_id'])->update([
                        'status_id' => $assignedStatus->id,
                        'updated_by' => $data['assigned_by'],
                    ]);
                }
            }

            // Update vehicle status to Assigned if assignment is active
            if (!empty($data['vehicle_id']) && $assignment->is_active) {
                $assignedStatus = Status::where('name', 'Assigned')->where('type', 'vehicle')->first();
                if ($assignedStatus) {
                    Vehicle::where('id', $data['vehicle_id'])->update([
                        'status_id' => $assignedStatus->id,
                        'updated_by' => $data['assigned_by'],
                    ]);
                }
            }

            // Update trailer status to Assigned if assignment is active
            if (!empty($data['trailer_id']) && $assignment->is_active) {
                $assignedStatus = Status::where('name', 'Assigned')->where('type', 'trailer')->first();
                if ($assignedStatus) {
                    Trailer::where('id', $data['trailer_id'])->update([
                        'status_id' => $assignedStatus->id,
                        'updated_by' => $data['assigned_by'],
                    ]);
                }
            }

            if ($assignment->is_active && $data['shipment_id']) {
                $this->advanceWaitingAssignmentShipment($data['shipment_id'], $data['assigned_by']);
            }

            return ApiResult::success($assignment->load(['shipment.status', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy']), 'Assignment created successfully');
        });

        if ($result->success && $result->data instanceof Assignment) {
            /** @var Assignment $assignment */
            $assignment = $result->data;
            if ($assignment->driver_id && $assignment->is_active) {
                DriverNotificationWriter::forAssignment(
                    $assignment,
                    DriverNotificationCode::ASSIGNMENT_ASSIGNED,
                    'New assignment',
                    'You have been assigned a new trip. Open assignment details to review.',
                    'info',
                    DriverNotificationAction::KIND_ASSIGNMENT
                );
            }
        }

        return $result;
    }

    /**
     * C2.9-C1.3 / C1.4 — Waiting Assignment → Assigned via transition boundary.
     */
    private function advanceWaitingAssignmentShipment(string $shipmentId, int $actorUserId): void
    {
        $shipment = Shipment::query()
            ->with(['status'])
            ->whereKey($shipmentId)
            ->first();

        if ($shipment === null
            || $shipment->status === null
            || $shipment->status->type !== ShipmentStatus::TYPE
            || $shipment->status->name !== ShipmentStatus::WAITING_ASSIGNMENT) {
            return;
        }

        $this->transitionShipmentStatusAction->execute(
            $shipment,
            ShipmentStatus::ASSIGNED,
            $actorUserId
        );
    }
}
