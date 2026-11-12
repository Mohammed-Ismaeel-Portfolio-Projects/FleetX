<?php

namespace App\Http\Controllers;

use App\Actions\Trailer\CreateTrailerAction;
use App\Actions\Trailer\DeleteTrailerAction;
use App\Actions\Trailer\GetTrailerAction;
use App\Actions\Trailer\QueryTrailersAction;
use App\Actions\Trailer\RestoreTrailerAction;
use App\Actions\Trailer\ToggleTrailerStatusAction;
use App\Actions\Trailer\UpdateTrailerAction;
use App\Core\ApiResponse;
use App\Http\Requests\Trailer\CreateTrailerRequest;
use App\Http\Requests\Trailer\QueryTrailersRequest;
use App\Http\Requests\Trailer\ToggleTrailerStatusRequest;
use App\Http\Requests\Trailer\UpdateTrailerRequest;
use App\Http\Resources\TrailerCollection;
use App\Http\Resources\TrailerResource;
use App\Models\Trailer;
use Illuminate\Http\JsonResponse;

class TrailerController extends Controller
{
    public function index(QueryTrailersRequest $request, QueryTrailersAction $action): JsonResponse
    {
        $this->authorize('view', Trailer::class);

        $result = $action->execute($request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new TrailerCollection($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function show(Trailer $trailer, GetTrailerAction $action): JsonResponse
    {
        $this->authorize('show', $trailer);

        $result = $action->execute($trailer->id);

        if ($result->success) {
            return ApiResponse::success(
                new TrailerResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function store(CreateTrailerRequest $request, CreateTrailerAction $action): JsonResponse
    {
        $this->authorize('create', Trailer::class);

        $result = $action->execute($request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new TrailerResource($result->data),
                $result->message,
                201
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function update(Trailer $trailer, UpdateTrailerRequest $request, UpdateTrailerAction $action): JsonResponse
    {
        $this->authorize('update', $trailer);

        $result = $action->execute($trailer, $request->validated());

        if ($result->success) {
            return ApiResponse::success(
                new TrailerResource($result->data),
                $result->message
            );
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function toggleStatus(Trailer $trailer, ToggleTrailerStatusRequest $request, ToggleTrailerStatusAction $action): JsonResponse
    {
        $this->authorize('toggleStatus', $trailer);

        $result = $action->execute($trailer, $request->validated()['is_active']);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function destroy(Trailer $trailer, DeleteTrailerAction $action): JsonResponse
    {
        $this->authorize('delete', $trailer);

        $result = $action->execute($trailer);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }

    public function restore(string $trailer, RestoreTrailerAction $action): JsonResponse
    {
        $this->authorize('restore', Trailer::class);

        $result = $action->execute($trailer);

        if ($result->success) {
            return ApiResponse::success($result->data, $result->message);
        }

        return ApiResponse::error($result->message, $result->statusCode);
    }
}
