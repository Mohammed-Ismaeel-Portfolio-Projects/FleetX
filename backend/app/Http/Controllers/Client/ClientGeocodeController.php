<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\ReverseGeocodeLocationAction;
use App\Actions\Client\SearchGeocodeLocationsAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ReverseGeocodeLocationRequest;
use App\Http\Requests\Client\SearchGeocodeLocationsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Client geocoding helpers for the request map picker.
 * Provider credentials stay server-side.
 */
class ClientGeocodeController extends Controller
{
    public function reverse(
        ReverseGeocodeLocationRequest $request,
        ReverseGeocodeLocationAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.create');

        $validated = $request->validated();
        $result = $action->execute((float) $validated['latitude'], (float) $validated['longitude']);

        return ApiResponse::success($result->data, $result->message);
    }

    public function search(
        SearchGeocodeLocationsRequest $request,
        SearchGeocodeLocationsAction $action
    ): JsonResponse {
        $this->authorize('client.shipmentRequest.create');

        $result = $action->execute((string) $request->validated()['query']);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message, $result->statusCode);
    }
}
