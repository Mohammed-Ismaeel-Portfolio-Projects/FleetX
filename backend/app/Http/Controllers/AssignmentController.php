<?php

namespace App\Http\Controllers;

use App\Actions\Assignment\ChangeAssignmentStatusAction;
use App\Actions\Assignment\CreateAssignmentAction;
use App\Actions\Assignment\DeleteAssignmentAction;
use App\Actions\Assignment\EnrollAssignmentInEngineAction;
use App\Actions\Assignment\GetAssignmentAction;
use App\Actions\Assignment\QueryAssignmentsAction;
use App\Actions\Assignment\RestoreAssignmentAction;
use App\Actions\Assignment\UpdateAssignmentAction;
use App\Core\ApiResponse;
use App\Http\Requests\Assignment\ChangeAssignmentStatusRequest;
use App\Http\Requests\Assignment\CreateAssignmentRequest;
use App\Http\Requests\Assignment\EnrollAssignmentInEngineRequest;
use App\Http\Requests\Assignment\QueryAssignmentsRequest;
use App\Http\Requests\Assignment\UpdateAssignmentRequest;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;

class AssignmentController extends Controller
{
    public function index(QueryAssignmentsRequest $request, QueryAssignmentsAction $action): JsonResponse
    {
        $this->authorize('viewAny', Assignment::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\AssignmentCollection($result->data),
            $result->message
        );
    }

    public function show(Assignment $assignment, GetAssignmentAction $action): JsonResponse
    {
        $this->authorize('view', $assignment);

        $result = $action->execute($assignment->id);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\AssignmentResource($result->data),
            $result->message
        );
    }

    public function store(CreateAssignmentRequest $request, CreateAssignmentAction $action): JsonResponse
    {
        $this->authorize('create', Assignment::class);

        $data = $request->validated();
        $data['assigned_by'] = auth()->id();

        $result = $action->execute($data);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\AssignmentResource($result->data),
            $result->message,
            201
        );
    }

    public function update(Assignment $assignment, UpdateAssignmentRequest $request, UpdateAssignmentAction $action): JsonResponse
    {
        $this->authorize('update', $assignment);

        $result = $action->execute($assignment, $request->validated(), auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\AssignmentResource($result->data),
            $result->message
        );
    }

    public function changeStatus(Assignment $assignment, ChangeAssignmentStatusRequest $request, ChangeAssignmentStatusAction $action): JsonResponse
    {
        $this->authorize('changeStatus', $assignment);

        $result = $action->execute(
            $assignment,
            $request->validated()['status_id'],
            auth()->id(),
            $request->validated()['ended_at'] ?? null
        );

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function destroy(Assignment $assignment, DeleteAssignmentAction $action): JsonResponse
    {
        $this->authorize('delete', $assignment);

        $result = $action->execute($assignment, auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function restore(string $assignment, RestoreAssignmentAction $action): JsonResponse
    {
        $this->authorize('restore', Assignment::class);

        $result = $action->execute($assignment, auth()->id());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    /**
     * Explicitly enroll an Assignment into the simulation Engine (C2.9-C2.8 Step 3).
     */
    public function enrollInEngine(
        EnrollAssignmentInEngineRequest $request,
        Assignment $assignment,
        EnrollAssignmentInEngineAction $action
    ): JsonResponse {
        $this->authorize('update', $assignment);

        $result = $action->execute($assignment->id);

        if (! $result->success) {
            if ($result->statusCode === 422 && $result->errors !== null) {
                return ApiResponse::validationError($result->errors, $result->message);
            }

            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new \App\Http\Resources\AssignmentResource($result->data),
            $result->message
        );
    }
}
