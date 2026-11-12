<?php

namespace App\Http\Controllers\Api\Shipment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipment\AssignShipmentRequest;
use App\Http\Requests\Shipment\CreateShipmentRequest;
use App\Http\Requests\Shipment\UpdateStatusRequest;
use App\Http\Resources\Shipment\ShipmentCollectionResource;
use App\Http\Resources\Shipment\ShipmentResource;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService
    ) {
    }

    /**
     * GET /api/shipments
     * Get all shipments with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'client_id', 'driver_id', 'start_date', 'end_date']);
        $shipments = $this->shipmentService->getShipments($filters);

        return (new ShipmentCollectionResource($shipments))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments
     * Create a new shipment from an order.
     */
    public function store(CreateShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipmentService->createShipment(
            $request->validated('order_id')
        );

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/shipments/{id}
     * Get a specific shipment by ID.
     */
    public function show(int $id): JsonResponse
    {
        $shipment = $this->shipmentService->getShipment($id);

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments/{id}/assign
     * Assign a shipment to a driver and truck.
     */
    public function assign(int $id, AssignShipmentRequest $request): JsonResponse
    {
        $shipment = $this->shipmentService->assignShipment(
            $id,
            $request->validated('driver_id'),
            $request->validated('truck_id'),
            $request->user()->id
        );

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments/{id}/status
     * Update shipment status with validation.
     */
    public function updateStatus(int $id, UpdateStatusRequest $request): JsonResponse
    {
        $shipment = $this->shipmentService->updateStatus(
            $id,
            $request->validated('status'),
            $request->validated('notes')
        );

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments/{id}/start
     * Start transit for a shipment.
     */
    public function startTransit(int $id): JsonResponse
    {
        $shipment = $this->shipmentService->startTransit($id);

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments/{id}/customs
     * Mark shipment as at customs.
     */
    public function markAtCustoms(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shipment = $this->shipmentService->markAtCustoms(
            $id,
            $request->input('notes')
        );

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * POST /api/shipments/{id}/deliver
     * Mark shipment as delivered.
     */
    public function markDelivered(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shipment = $this->shipmentService->markDelivered(
            $id,
            $request->input('notes')
        );

        return (new ShipmentResource($shipment))
            ->response()
            ->setStatusCode(200);
    }
}
