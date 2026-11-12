<?php

namespace App\Actions\Assignment;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Trailer;
use App\Models\Status;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Illuminate\Support\Facades\DB;

class UpdateAssignmentAction
{
    public function execute(Assignment $assignment, array $data, int $userId): ApiResult
    {
        $previousDriverId = $assignment->driver_id;

        $result = DB::transaction(function () use ($assignment, $data, $userId) {
            // Check if shipment is being changed and if new shipment already has an active assignment
            if (isset($data['shipment_id']) && $data['shipment_id'] !== $assignment->shipment_id) {
                $existingShipmentAssignment = Assignment::where('shipment_id', $data['shipment_id'])
                    ->where('id', '!=', $assignment->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingShipmentAssignment) {
                    return ApiResult::error('Shipment already has an active assignment', 422);
                }
            }

            // Check if driver is being changed and if new driver is already assigned
            if (isset($data['driver_id']) && $data['driver_id'] !== $assignment->driver_id) {
                $existingDriverAssignment = Assignment::where('driver_id', $data['driver_id'])
                    ->where('id', '!=', $assignment->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingDriverAssignment) {
                    return ApiResult::error('Driver is already assigned to an active assignment', 422);
                }
            }

            // Check if vehicle is being changed and if new vehicle is already assigned
            if (isset($data['vehicle_id']) && $data['vehicle_id'] !== $assignment->vehicle_id) {
                $existingVehicleAssignment = Assignment::where('vehicle_id', $data['vehicle_id'])
                    ->where('id', '!=', $assignment->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingVehicleAssignment) {
                    return ApiResult::error('Vehicle is already assigned to an active assignment', 422);
                }
            }

            // Check if trailer is being changed and if new trailer is already assigned
            if (isset($data['trailer_id']) && $data['trailer_id'] !== $assignment->trailer_id) {
                $existingTrailerAssignment = Assignment::where('trailer_id', $data['trailer_id'])
                    ->where('id', '!=', $assignment->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingTrailerAssignment) {
                    return ApiResult::error('Trailer is already assigned to an active assignment', 422);
                }
            }

            // Filter only changed fields
            $changedData = array_filter($data, function ($value, $key) use ($assignment) {
                return $assignment->$key !== $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($changedData)) {
                return ApiResult::error('No changes detected', 400);
            }

            // Update assignment
            $changedData['updated_by'] = $userId;
            $assignment->update($changedData);

            // Handle driver status changes
            if (isset($data['driver_id']) && $data['driver_id'] !== $assignment->driver_id) {
                // Restore old driver to Available
                $availableStatus = Status::where('name', 'Available')->where('type', 'driver')->first();
                if ($availableStatus && $assignment->driver_id) {
                    Driver::where('id', $assignment->driver_id)->update([
                        'status_id' => $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }

                // Set new driver to Assigned if assignment is active
                if ($assignment->is_active) {
                    $assignedStatus = Status::where('name', 'Assigned')->where('type', 'driver')->first();
                    if ($assignedStatus) {
                        Driver::where('id', $data['driver_id'])->update([
                            'status_id' => $assignedStatus->id,
                            'updated_by' => $userId,
                        ]);
                    }
                }
            }

            // Handle vehicle status changes
            if (isset($data['vehicle_id']) && $data['vehicle_id'] !== $assignment->vehicle_id) {
                // Restore old vehicle to Available
                $availableStatus = Status::where('name', 'Available')->where('type', 'vehicle')->first();
                if ($availableStatus && $assignment->vehicle_id) {
                    Vehicle::where('id', $assignment->vehicle_id)->update([
                        'status_id' => $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }

                // Set new vehicle to Assigned if assignment is active
                if ($assignment->is_active) {
                    $assignedStatus = Status::where('name', 'Assigned')->where('type', 'vehicle')->first();
                    if ($assignedStatus) {
                        Vehicle::where('id', $data['vehicle_id'])->update([
                            'status_id' => $assignedStatus->id,
                            'updated_by' => $userId,
                        ]);
                    }
                }
            }

            // Handle trailer status changes
            if (isset($data['trailer_id']) && $data['trailer_id'] !== $assignment->trailer_id) {
                // Restore old trailer to Available
                $availableStatus = Status::where('name', 'Available')->where('type', 'trailer')->first();
                if ($availableStatus && $assignment->trailer_id) {
                    Trailer::where('id', $assignment->trailer_id)->update([
                        'status_id' => $availableStatus->id,
                        'updated_by' => $userId,
                    ]);
                }

                // Set new trailer to Assigned if assignment is active
                if ($assignment->is_active) {
                    $assignedStatus = Status::where('name', 'Assigned')->where('type', 'trailer')->first();
                    if ($assignedStatus) {
                        Trailer::where('id', $data['trailer_id'])->update([
                            'status_id' => $assignedStatus->id,
                            'updated_by' => $userId,
                        ]);
                    }
                }
            }

            return ApiResult::success($assignment->load(['shipment', 'driver', 'vehicle', 'trailer', 'status', 'assignedBy']), 'Assignment updated successfully');
        });

        if ($result->success && $result->data instanceof Assignment) {
            /** @var Assignment $fresh */
            $fresh = $result->data;
            $driverChanged = isset($data['driver_id']) && (int) $data['driver_id'] !== (int) $previousDriverId;

            if ($driverChanged) {
                if ($previousDriverId) {
                    DriverNotificationWriter::notify(
                        (int) $previousDriverId,
                        DriverNotificationCode::ASSIGNMENT_REASSIGNED,
                        'Assignment reassigned',
                        'Your assignment was reassigned. Open Work for current status.',
                        'critical',
                        $fresh,
                        DriverNotificationAction::KIND_WORK,
                        null,
                        DriverNotificationCode::ASSIGNMENT_REASSIGNED.':'.$fresh->id.':from:'.$previousDriverId
                    );
                }
                if ($fresh->driver_id) {
                    DriverNotificationWriter::forAssignment(
                        $fresh,
                        DriverNotificationCode::ASSIGNMENT_ASSIGNED,
                        'New assignment',
                        'You have been assigned a trip. Open assignment details to review.',
                        'info',
                        DriverNotificationAction::KIND_ASSIGNMENT,
                        'reassign'
                    );
                }
            } elseif ($fresh->driver_id) {
                DriverNotificationWriter::forAssignment(
                    $fresh,
                    DriverNotificationCode::ASSIGNMENT_CHANGED,
                    'Assignment updated',
                    'Your assignment details were updated. Review the latest information.',
                    'info',
                    DriverNotificationAction::KIND_ASSIGNMENT,
                    (string) time()
                );
            }
        }

        return $result;
    }
}
