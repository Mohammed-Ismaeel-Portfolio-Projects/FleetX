<?php

namespace App\Http\Controllers;

use App\Actions\Shipment\GetShipmentRouteAction;
use App\Core\ApiResponse;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RouteController
 *
 * Handles route-related API endpoints.
 * Provides route information for shipments using the Routing module.
 */
class RouteController extends Controller
{
    /**
     * Get route for a specific shipment.
     *
     * Immutable by default. Pass recalculate=1 for an explicit dispatcher recalculation.
     */
    public function show(Request $request, Shipment $shipment, GetShipmentRouteAction $action): JsonResponse
    {
        $this->authorize('view', $shipment);

        $forceRecalculate = $request->boolean('recalculate');
        $result = $action->execute($shipment->id, $forceRecalculate);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }
}
