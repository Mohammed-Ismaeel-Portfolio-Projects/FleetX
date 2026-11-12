<?php

namespace App\Http\Controllers\Dispatcher;

use App\Actions\Dispatcher\GetTrackingSessionForShipmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatcher\StartTrackingSessionRequest;
use App\Http\Requests\Dispatcher\TrackingSessionOverrideRequest;
use App\Http\Resources\TrackingSessionEventResource;
use App\Http\Resources\TrackingSessionResource;
use App\Models\Assignment;
use App\Models\ShipmentOrder;
use App\Models\TrackingSession;
use App\Models\TrackingSessionEvent;
use App\Services\Tracking\ShipmentTrackingEngine;
use App\Services\Tracking\TrackingSessionEventLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class TrackingSessionController extends Controller
{
    public function __construct(
        private TrackingSessionEventLogger $eventLogger,
        private ShipmentTrackingEngine $trackingEngine
    ) {}

    protected function authorizeDispatcher(Request $request): void
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user || ! in_array($user->role?->name, ['dispatcher', 'admin'], true)) {
            abort(403, 'Unauthorized. Dispatcher or Admin role required.');
        }
    }

    private function tickSessionWithLock(TrackingSession $session): void
    {
        if ($session->status !== TrackingSession::STATUS_ACTIVE) {
            return;
        }

        $lockKey = "tracking_session:{$session->id}:tick-lock";
        $lock = Cache::lock($lockKey, 4);

        if ($lock->get()) {
            try {
                $this->trackingEngine->tick($session);
            } finally {
                $lock->release();
            }
        }
    }

    public function showForShipment(
        Request $request,
        ShipmentOrder $shipmentOrder,
        GetTrackingSessionForShipmentAction $action
    ): JsonResponse {
        $this->authorizeDispatcher($request);

        $session = $action->execute($shipmentOrder);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No tracking session found for this shipment order.',
            ], 404);
        }

        return (new TrackingSessionResource($session))
            ->additional([
                'success' => true,
            ])
            ->response();
    }

    public function start(StartTrackingSessionRequest $request): JsonResponse
    {
        $this->authorizeDispatcher($request);

        $shipmentOrder = ShipmentOrder::findOrFail($request->validated('shipment_order_id'));

        $shipmentId = $shipmentOrder->converted_shipment_id;
        $assignment = null;

        if ($shipmentId) {
            $assignment = Assignment::where('shipment_id', $shipmentId)
                ->where('is_active', true)
                ->first();
        }

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start tracking session without an active vehicle and driver assignment.',
            ], 422);
        }

        $session = TrackingSession::where('shipment_order_id', $shipmentOrder->id)->first();
        if (! $session) {
            $session = new TrackingSession();
            $session->shipment_order_id = $shipmentOrder->id;
        }

        $session->shipment_id = $shipmentId;
        $session->assignment_id = $assignment->id;
        $session->driver_id = $assignment->driver_id;
        $session->vehicle_id = $assignment->vehicle_id;
        $session->status = TrackingSession::STATUS_ACTIVE;
        $session->started_at = $session->started_at ?? now();
        $session->paused_at = null;
        $session->save();

        $this->eventLogger->log($session, TrackingSessionEvent::EVENT_STARTED);

        // Immediate tick to generate initial telemetry point and broadcast WS event
        $this->tickSessionWithLock($session);

        return (new TrackingSessionResource($session->fresh(['driver', 'vehicle', 'assignment.trailer'])))
            ->additional([
                'success' => true,
                'message' => 'Tracking session started successfully',
            ])
            ->response();
    }

    public function pause(Request $request, TrackingSession $session): JsonResponse
    {
        $this->authorizeDispatcher($request);

        if ($session->status !== TrackingSession::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Only ACTIVE sessions can be paused.',
            ], 422);
        }

        $session->status = TrackingSession::STATUS_PAUSED;
        $session->paused_at = now();
        $session->save();

        $this->eventLogger->log($session, TrackingSessionEvent::EVENT_PAUSED);

        return (new TrackingSessionResource($session->fresh(['driver', 'vehicle', 'assignment.trailer'])))
            ->additional([
                'success' => true,
                'message' => 'Tracking session paused successfully',
            ])
            ->response();
    }

    public function resume(Request $request, TrackingSession $session): JsonResponse
    {
        $this->authorizeDispatcher($request);

        if ($session->status !== TrackingSession::STATUS_PAUSED) {
            return response()->json([
                'success' => false,
                'message' => 'Only PAUSED sessions can be resumed.',
            ], 422);
        }

        $session->status = TrackingSession::STATUS_ACTIVE;
        $session->paused_at = null;
        $session->save();

        $this->eventLogger->log($session, TrackingSessionEvent::EVENT_RESUMED);

        // Immediate tick on resume
        $this->tickSessionWithLock($session);

        return (new TrackingSessionResource($session->fresh(['driver', 'vehicle', 'assignment.trailer'])))
            ->additional([
                'success' => true,
                'message' => 'Tracking session resumed successfully',
            ])
            ->response();
    }

    public function stop(Request $request, TrackingSession $session): JsonResponse
    {
        $this->authorizeDispatcher($request);

        $session->status = TrackingSession::STATUS_TERMINATED;
        $session->ended_at = now();
        $session->save();

        Cache::forget("tracking_session:{$session->id}:progress");

        $this->eventLogger->log($session, TrackingSessionEvent::EVENT_STOPPED);

        return (new TrackingSessionResource($session->fresh(['driver', 'vehicle', 'assignment.trailer'])))
            ->additional([
                'success' => true,
                'message' => 'Tracking session stopped successfully',
            ])
            ->response();
    }

    public function override(TrackingSessionOverrideRequest $request, TrackingSession $session): JsonResponse
    {
        $this->authorizeDispatcher($request);

        $data = $request->validated();

        if (array_key_exists('forced_speed_kmh', $data)) {
            $session->forced_speed_kmh = $data['forced_speed_kmh'];
        }

        if (array_key_exists('forced_deceleration', $data)) {
            $session->forced_deceleration = (bool) $data['forced_deceleration'];
        }

        if (array_key_exists('forced_route_deviation', $data)) {
            $session->forced_route_deviation = (bool) $data['forced_route_deviation'];
        }

        $session->save();

        // Audit Logging Logic
        $hasSpeedOverride = array_key_exists('forced_speed_kmh', $data) && $data['forced_speed_kmh'] !== null;
        $isDecelerationTriggered = ! empty($data['forced_deceleration']);
        $isRouteDeviationTriggered = ! empty($data['forced_route_deviation']);

        $isReset = ($session->forced_speed_kmh === null || (array_key_exists('forced_speed_kmh', $data) && $data['forced_speed_kmh'] === null))
            && ! $session->forced_deceleration
            && ! $session->forced_route_deviation;

        if ($hasSpeedOverride) {
            $this->eventLogger->log($session, TrackingSessionEvent::EVENT_SPEED_OVERRIDE, [
                'forced_speed_kmh' => (float) $data['forced_speed_kmh'],
            ]);
        }

        if ($isDecelerationTriggered) {
            $this->eventLogger->log($session, TrackingSessionEvent::EVENT_SUDDEN_STOP_TRIGGERED, [
                'forced_deceleration' => true,
            ]);
        }

        if ($isRouteDeviationTriggered) {
            $this->eventLogger->log($session, TrackingSessionEvent::EVENT_ROUTE_DEVIATION_TRIGGERED, [
                'forced_route_deviation' => true,
            ]);
        }

        if ($isReset && ! $hasSpeedOverride && ! $isDecelerationTriggered && ! $isRouteDeviationTriggered) {
            $this->eventLogger->log($session, TrackingSessionEvent::EVENT_OVERRIDE_RESET);
        }

        // Tick session immediately when override changes (if session active)
        $this->tickSessionWithLock($session);

        return (new TrackingSessionResource($session->fresh(['driver', 'vehicle', 'assignment.trailer'])))
            ->additional([
                'success' => true,
                'message' => 'Tracking session override updated successfully',
            ])
            ->response();
    }

    public function events(Request $request, TrackingSession $session): AnonymousResourceCollection
    {
        $this->authorizeDispatcher($request);

        $events = $session->events()->orderByDesc('id')->get();

        return TrackingSessionEventResource::collection($events);
    }
}
