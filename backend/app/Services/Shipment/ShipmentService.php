<?php

namespace App\Services\Shipment;

use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\TrackingLog;
use App\Models\Truck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShipmentService
{
    /**
     * Valid status transitions for shipments.
     *
     * Key = current status, Value = array of allowed next statuses.
     */
    private const VALID_TRANSITIONS = [
        'pending' => ['assigned', 'cancelled'],
        'assigned' => ['in_transit', 'cancelled'],
        'in_transit' => ['at_customs', 'delivered'],
        'at_customs' => ['in_transit', 'delivered'],
        'delivered' => [], // Terminal state
        'cancelled' => [], // Terminal state
    ];

    /**
     * Create a new shipment from an order.
     *
     * @param int $orderId
     * @return Shipment
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Exception
     */
    public function createShipment(int $orderId): Shipment
    {
        $order = \App\Models\Order::with(['orderItems', 'pickupLocation', 'deliveryLocation'])
            ->findOrFail($orderId);

        // Validate order status
        if ($order->status->name !== 'Approved') {
            throw new \Exception('Cannot create shipment from order with status: ' . $order->status->name);
        }

        return DB::transaction(function () use ($order) {
            // Calculate total weight from order items
            $totalWeight = $order->orderItems->sum(function ($item) {
                return $item->weight * $item->quantity;
            });

            // Get pending status for shipment
            $pendingStatus = Status::where('name', 'pending')
                ->where('type', 'shipment')
                ->firstOrFail();

            // Create shipment
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'status_id' => $pendingStatus->id,
                'total_weight' => $totalWeight,
            ]);

            // Log initial status
            $this->logTrackingEntry($shipment, 'pending', 'Shipment created from order');

            Log::info('Shipment created', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'total_weight' => $totalWeight,
            ]);

            return $shipment->load(['order', 'status']);
        });
    }

    /**
     * Assign a shipment to a driver and truck.
     *
     * @param int $shipmentId
     * @param int $driverId
     * @param int $truckId
     * @param int $assignedBy
     * @return Shipment
     * @throws \Exception
     */
    public function assignShipment(int $shipmentId, int $driverId, int $truckId, int $assignedBy): Shipment
    {
        return DB::transaction(function () use ($shipmentId, $driverId, $truckId, $assignedBy) {
            $shipment = Shipment::with(['status'])->findOrFail($shipmentId);
            $driver = Driver::with(['status', 'user'])->findOrFail($driverId);
            $truck = Truck::with('status')->findOrFail($truckId);

            // Validate shipment status
            if ($shipment->status->name !== 'pending') {
                throw new \Exception('Cannot assign shipment with status: ' . $shipment->status->name);
            }

            // Validate driver availability
            if ($driver->status->name !== 'Available') {
                throw new \Exception('Driver is not available. Current status: ' . $driver->status->name);
            }

            // Validate truck availability
            if ($truck->status->name !== 'Available') {
                throw new \Exception('Truck is not available. Current status: ' . $truck->status->name);
            }

            // Check for existing active assignment on shipment
            $existingAssignment = Assignment::where('shipment_id', $shipmentId)
                ->whereNull('ended_at')
                ->first();
            if ($existingAssignment) {
                throw new \Exception('Shipment already has an active assignment');
            }

            // Get required statuses
            $assignedStatus = Status::where('name', 'assigned')
                ->where('type', 'shipment')
                ->firstOrFail();
            $busyStatus = Status::where('name', 'Busy')
                ->where('type', 'driver')
                ->firstOrFail();
            $inServiceStatus = Status::where('name', 'In Service')
                ->where('type', 'truck')
                ->firstOrFail();
            $activeAssignmentStatus = Status::where('name', 'Active')
                ->where('type', 'assignment')
                ->firstOrFail();

            // Create assignment
            $assignment = Assignment::create([
                'shipment_id' => $shipmentId,
                'driver_id' => $driverId,
                'truck_id' => $truckId,
                'assigned_by' => $assignedBy,
                'status_id' => $activeAssignmentStatus->id,
                'assigned_at' => now(),
            ]);

            // Update shipment status
            $shipment->update(['status_id' => $assignedStatus->id]);

            // Lock driver and truck
            $driver->update(['status_id' => $busyStatus->id]);
            $truck->update(['status_id' => $inServiceStatus->id]);

            // Log tracking entry
            $this->logTrackingEntry(
                $shipment,
                'assigned',
                "Assigned to driver {$driver->user->name} (truck {$truck->plate_number})"
            );

            Log::info('Shipment assigned', [
                'shipment_id' => $shipmentId,
                'driver_id' => $driverId,
                'truck_id' => $truckId,
                'assigned_by' => $assignedBy,
                'assignment_id' => $assignment->id,
            ]);

            return $shipment->load(['order', 'status', 'assignments.driver.user', 'assignments.truck']);
        });
    }

    /**
     * Update shipment status with validation.
     *
     * @param int $shipmentId
     * @param string $newStatus
     * @param string|null $notes
     * @return Shipment
     * @throws \Exception
     */
    public function updateStatus(int $shipmentId, string $newStatus, ?string $notes = null): Shipment
    {
        $shipment = Shipment::with('status')->findOrFail($shipmentId);
        $currentStatus = $shipment->status->name;

        // Validate transition
        if (!$this->isValidTransition($currentStatus, $newStatus)) {
            throw new \Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
        }

        return DB::transaction(function () use ($shipment, $newStatus, $notes, $currentStatus) {
            $status = Status::where('name', $newStatus)
                ->where('type', 'shipment')
                ->firstOrFail();

            $shipment->update(['status_id' => $status->id]);

            // Log tracking entry
            $this->logTrackingEntry($shipment, $newStatus, $notes ?? "Status changed from {$currentStatus} to {$newStatus}");

            Log::info('Shipment status updated', [
                'shipment_id' => $shipment->id,
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]);

            return $shipment->load(['order', 'status']);
        });
    }

    /**
     * Start transit for a shipment.
     *
     * @param int $shipmentId
     * @return Shipment
     * @throws \Exception
     */
    public function startTransit(int $shipmentId): Shipment
    {
        return $this->updateStatus($shipmentId, 'in_transit', 'Transit started');
    }

    /**
     * Mark shipment as at customs.
     *
     * @param int $shipmentId
     * @param string|null $notes
     * @return Shipment
     * @throws \Exception
     */
    public function markAtCustoms(int $shipmentId, ?string $notes = null): Shipment
    {
        $shipment = Shipment::with('status')->findOrFail($shipmentId);

        // Business rule: can only mark at customs if in_transit
        if ($shipment->status->name !== 'in_transit') {
            throw new \Exception('Shipment must be in_transit to mark as at_customs');
        }

        return $this->updateStatus($shipmentId, 'at_customs', $notes ?? 'Arrived at customs checkpoint');
    }

    /**
     * Mark shipment as delivered.
     *
     * @param int $shipmentId
     * @param string|null $notes
     * @return Shipment
     * @throws \Exception
     */
    public function markDelivered(int $shipmentId, ?string $notes = null): Shipment
    {
        $shipment = Shipment::with('status')->findOrFail($shipmentId);

        // Business rule: can only deliver if in_transit or at_customs
        if (!in_array($shipment->status->name, ['in_transit', 'at_customs'])) {
            throw new \Exception('Shipment must be in_transit or at_customs to be marked as delivered');
        }

        return DB::transaction(function () use ($shipment, $shipmentId, $notes) {
            $shipment = $this->updateStatus($shipmentId, 'delivered', $notes ?? 'Shipment delivered successfully');

            // Update timestamps
            $shipment->update([
                'delivery_time' => now(),
                'end_time' => now(),
            ]);

            // Complete active assignment
            $activeAssignment = Assignment::where('shipment_id', $shipmentId)
                ->whereNull('ended_at')
                ->first();

            ifActiveAssignment($activeAssignment, function () use ($activeAssignment) {
                $completedStatus = Status::where('name', 'Completed')
                    ->where('type', 'assignment')
                    ->firstOrFail();

                $activeAssignment->update([
                    'status_id' => $completedStatus->id,
                    'ended_at' => now(),
                ]);

                // Release driver and truck
                $availableDriverStatus = Status::where('name', 'Available')
                    ->where('type', 'driver')
                    ->firstOrFail();
                $availableTruckStatus = Status::where('name', 'Available')
                    ->where('type', 'truck')
                    ->firstOrFail();

                $activeAssignment->driver->update(['status_id' => $availableDriverStatus->id]);
                $activeAssignment->truck->update(['status_id' => $availableTruckStatus->id]);
            });

            return $shipment->load(['order', 'status', 'assignments']);
        });
    }

    /**
     * Cancel a shipment.
     *
     * @param int $shipmentId
     * @param string|null $reason
     * @return Shipment
     * @throws \Exception
     */
    public function cancelShipment(int $shipmentId, ?string $reason = null): Shipment
    {
        $shipment = Shipment::with('status')->findOrFail($shipmentId);

        // Cannot cancel delivered shipments
        if ($shipment->status->name === 'delivered') {
            throw new \Exception('Cannot cancel a delivered shipment');
        }

        return DB::transaction(function () use ($shipment, $shipmentId, $reason) {
            $shipment = $this->updateStatus($shipmentId, 'cancelled', $reason ?? 'Shipment cancelled');

            // End active assignment if exists
            $activeAssignment = Assignment::where('shipment_id', $shipmentId)
                ->whereNull('ended_at')
                ->first();

            ifActiveAssignment($activeAssignment, function () use ($activeAssignment) {
                $cancelledStatus = Status::where('name', 'Cancelled')
                    ->where('type', 'assignment')
                    ->firstOrFail();

                $activeAssignment->update([
                    'status_id' => $cancelledStatus->id,
                    'ended_at' => now(),
                ]);

                // Release driver and truck
                $availableDriverStatus = Status::where('name', 'Available')
                    ->where('type', 'driver')
                    ->firstOrFail();
                $availableTruckStatus = Status::where('name', 'Available')
                    ->where('type', 'truck')
                    ->firstOrFail();

                $activeAssignment->driver->update(['status_id' => $availableDriverStatus->id]);
                $activeAssignment->truck->update(['status_id' => $availableTruckStatus->id]);
            });

            return $shipment->load(['order', 'status', 'assignments']);
        });
    }

    /**
     * Get shipment by ID with relationships.
     *
     * @param int $shipmentId
     * @return Shipment
     */
    public function getShipment(int $shipmentId): Shipment
    {
        return Shipment::with([
            'order.client',
            'order.pickupLocation',
            'order.deliveryLocation',
            'order.orderItems',
            'status',
            'assignments.driver.user',
            'assignments.truck',
            'assignments.status',
            'route.routePoints',
            'trackingLogs',
            'deliveryProof',
        ])->findOrFail($shipmentId);
    }

    /**
     * Get all shipments with optional filtering.
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getShipments(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Shipment::with([
            'order.client',
            'status',
            'assignments.driver.user',
            'assignments.truck',
        ]);

        // Filter by status
        if (isset($filters['status'])) {
            $query->whereHas('status', function (Builder $q) use ($filters) {
                $q->where('name', $filters['status']);
            });
        }

        // Filter by client
        if (isset($filters['client_id'])) {
            $query->whereHas('order', function (Builder $q) use ($filters) {
                $q->where('client_id', $filters['client_id']);
            });
        }

        // Filter by driver
        if (isset($filters['driver_id'])) {
            $query->whereHas('assignments', function (Builder $q) use ($filters) {
                $q->where('driver_id', $filters['driver_id'])->whereNull('ended_at');
            });
        }

        // Filter by date range
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Validate if a status transition is allowed.
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    private function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        // Same status is not a transition
        if ($currentStatus === $newStatus) {
            return false;
        }

        $validTransitions = self::VALID_TRANSITIONS[$currentStatus] ?? [];
        return in_array($newStatus, $validTransitions, true);
    }

    /**
     * Log a tracking entry for status changes.
     *
     * @param Shipment $shipment
     * @param string $status
     * @param string $notes
     * @return void
     */
    private function logTrackingEntry(Shipment $shipment, string $status, string $notes): void
    {
        TrackingLog::create([
            'shipment_id' => $shipment->id,
            'latitude' => $shipment->current_lat ?? 0,
            'longitude' => $shipment->current_lng ?? 0,
            'speed' => null,
            'heading' => null,
            'accuracy' => null,
            'recorded_at' => now(),
        ]);

        // Note: The tracking_logs table doesn't have a status/notes field.
        // For status change tracking, we could add these fields or use audit_logs.
        // For now, we log to the application log as well.
        Log::info('Shipment tracking entry', [
            'shipment_id' => $shipment->id,
            'status' => $status,
            'notes' => $notes,
            'timestamp' => now(),
        ]);
    }
}

/**
 * Helper function to execute callback if assignment exists.
 */
function ifActiveAssignment($assignment, callable $callback): void
{
    if ($assignment) {
        $callback();
    }
}
