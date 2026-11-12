<?php

namespace App\Http\Controllers\Driver;

use App\Actions\Driver\AcknowledgeDriverAssignmentAction;
use App\Actions\Driver\CompleteDriverAssignmentAction;
use App\Actions\Driver\CreateDriverIssueAction;
use App\Actions\Driver\FailDriverDeliveryAction;
use App\Actions\Driver\GetDriverActiveAssignmentAction;
use App\Actions\Driver\GetDriverAssignmentAction;
use App\Actions\Driver\GetDriverOutcomeAction;
use App\Actions\Driver\GetDriverTripMapAction;
use App\Actions\Driver\StartDriverAssignmentAction;
use App\Actions\Driver\SubmitDriverPodAction;
use App\Actions\Driver\SubmitDriverTrackingAction;
use App\Actions\Driver\TransitionDriverExecutionPhaseAction;
use App\Actions\Driver\VerifyDriverAssignmentAction;
use App\Core\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\AcknowledgeDriverAssignmentRequest;
use App\Http\Requests\Driver\CompleteDriverAssignmentRequest;
use App\Http\Requests\Driver\CreateDriverIssueRequest;
use App\Http\Requests\Driver\FailDriverDeliveryRequest;
use App\Http\Requests\Driver\ShowDriverActiveWorkRequest;
use App\Http\Requests\Driver\ShowDriverAssignmentRequest;
use App\Http\Requests\Driver\ShowDriverOutcomeRequest;
use App\Http\Requests\Driver\ShowDriverTripMapRequest;
use App\Http\Requests\Driver\StartDriverAssignmentRequest;
use App\Http\Requests\Driver\SubmitDriverPodRequest;
use App\Http\Requests\Driver\SubmitDriverTrackingRequest;
use App\Http\Requests\Driver\TransitionDriverExecutionPhaseRequest;
use App\Http\Requests\Driver\VerifyDriverAssignmentRequest;
use App\Http\Resources\Driver\DriverWorkAssignmentResource;
use App\Support\DriverVerificationEvent;
use Illuminate\Http\JsonResponse;

/**
 * Driver-facing work surface (D2 read + D3 acknowledge/start + D4 execution phases).
 * Distinct from ops AssignmentController — never widens assignment.* mutation gates.
 */
class DriverWorkController extends Controller
{
    public function active(
        ShowDriverActiveWorkRequest $request,
        GetDriverActiveAssignmentAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        $assignment = $result->data;

        return ApiResponse::success(
            $assignment ? new DriverWorkAssignmentResource($assignment) : null,
            $result->message
        );
    }

    public function show(
        ShowDriverAssignmentRequest $request,
        string $assignment,
        GetDriverAssignmentAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new DriverWorkAssignmentResource($result->data),
            $result->message
        );
    }

    public function acknowledge(
        AcknowledgeDriverAssignmentRequest $request,
        string $assignment,
        AcknowledgeDriverAssignmentAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new DriverWorkAssignmentResource($result->data),
            $result->message
        );
    }

    public function start(
        StartDriverAssignmentRequest $request,
        string $assignment,
        StartDriverAssignmentAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new DriverWorkAssignmentResource($result->data),
            $result->message
        );
    }

    public function pickupArrive(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'pickup_arrive');
    }

    public function pickupLoading(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'pickup_loading');
    }

    public function pickupLoadingComplete(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'pickup_loading_complete');
    }

    public function deliveryArrive(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'delivery_arrive');
    }

    public function deliveryUnloading(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'delivery_unloading');
    }

    public function deliveryUnloadingComplete(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action
    ): JsonResponse {
        return $this->transitionPhase($request, $assignment, $action, 'delivery_unloading_complete');
    }

    public function pickupVerify(
        VerifyDriverAssignmentRequest $request,
        string $assignment,
        VerifyDriverAssignmentAction $action
    ): JsonResponse {
        return $this->verify($request, $assignment, $action, DriverVerificationEvent::PICKUP_VERIFY);
    }

    public function deliveryVerify(
        VerifyDriverAssignmentRequest $request,
        string $assignment,
        VerifyDriverAssignmentAction $action
    ): JsonResponse {
        return $this->verify($request, $assignment, $action, DriverVerificationEvent::DELIVERY_VERIFY);
    }

    public function submitTracking(
        SubmitDriverTrackingRequest $request,
        string $assignment,
        SubmitDriverTrackingAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function tripMap(
        ShowDriverTripMapRequest $request,
        string $assignment,
        GetDriverTripMapAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function outcome(
        ShowDriverOutcomeRequest $request,
        string $assignment,
        GetDriverOutcomeAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{assignment: \App\Models\Assignment, outcome: array<string, mixed>} $data */
        $data = $result->data;

        return ApiResponse::success([
            'assignment' => new DriverWorkAssignmentResource($data['assignment']),
            'outcome' => $data['outcome'],
        ], $result->message);
    }

    public function submitPod(
        SubmitDriverPodRequest $request,
        string $assignment,
        SubmitDriverPodAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute(
            $user,
            $assignment,
            $request->file('photo'),
            $request->file('signature'),
            $request->validated()
        );

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{assignment: \App\Models\Assignment, pod: array<string, mixed>} $data */
        $data = $result->data;

        return ApiResponse::success([
            'assignment' => new DriverWorkAssignmentResource($data['assignment']),
            'pod' => $data['pod'],
        ], $result->message);
    }

    public function complete(
        CompleteDriverAssignmentRequest $request,
        string $assignment,
        CompleteDriverAssignmentAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{assignment: \App\Models\Assignment, completed: bool, completed_at: ?string} $data */
        $data = $result->data;

        return ApiResponse::success([
            'assignment' => new DriverWorkAssignmentResource($data['assignment']),
            'completed' => $data['completed'],
            'completed_at' => $data['completed_at'] ?? null,
        ], $result->message);
    }

    public function createIssue(
        CreateDriverIssueRequest $request,
        string $assignment,
        CreateDriverIssueAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message, 201);
    }

    public function failDelivery(
        FailDriverDeliveryRequest $request,
        string $assignment,
        FailDriverDeliveryAction $action
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{assignment: \App\Models\Assignment, failed: bool} $data */
        $data = $result->data;

        return ApiResponse::success([
            'assignment' => new DriverWorkAssignmentResource($data['assignment']),
            'failed' => $data['failed'],
        ], $result->message);
    }

    private function verify(
        VerifyDriverAssignmentRequest $request,
        string $assignment,
        VerifyDriverAssignmentAction $action,
        string $event
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute(
            $user,
            $assignment,
            $event,
            (string) $request->validated('challenge')
        );

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        /** @var array{assignment: \App\Models\Assignment, verification: array<string, mixed>} $data */
        $data = $result->data;

        return ApiResponse::success([
            'assignment' => new DriverWorkAssignmentResource($data['assignment']),
            'verification' => $data['verification'],
        ], $result->message);
    }

    private function transitionPhase(
        TransitionDriverExecutionPhaseRequest $request,
        string $assignment,
        TransitionDriverExecutionPhaseAction $action,
        string $command
    ): JsonResponse {
        $this->authorize('driver.work.view');

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $action->execute($user, $assignment, $command);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            new DriverWorkAssignmentResource($result->data),
            $result->message
        );
    }
}
