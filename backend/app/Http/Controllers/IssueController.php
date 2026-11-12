<?php

namespace App\Http\Controllers;

use App\Actions\Issue\CreateIssueAction;
use App\Actions\Issue\DeleteIssueAction;
use App\Actions\Issue\GetIssueAction;
use App\Actions\Issue\QueryIssuesAction;
use App\Actions\Issue\UpdateIssueAction;
use App\Core\ApiResponse;
use App\Http\Requests\Issue\CreateIssueRequest;
use App\Http\Requests\Issue\QueryIssuesRequest;
use App\Http\Requests\Issue\UpdateIssueRequest;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;

class IssueController extends Controller
{
    public function index(QueryIssuesRequest $request, QueryIssuesAction $action): JsonResponse
    {
        $this->authorize('viewAny', Issue::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function show(Issue $issue, GetIssueAction $action): JsonResponse
    {
        $this->authorize('view', $issue);

        $result = $action->execute($issue->id);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function store(CreateIssueRequest $request, CreateIssueAction $action): JsonResponse
    {
        $this->authorize('create', Issue::class);

        $result = $action->execute($request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message,
            201
        );
    }

    public function update(Issue $issue, UpdateIssueRequest $request, UpdateIssueAction $action): JsonResponse
    {
        $this->authorize('update', $issue);

        $result = $action->execute($issue, $request->validated());

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success(
            $result->data,
            $result->message
        );
    }

    public function destroy(Issue $issue, DeleteIssueAction $action): JsonResponse
    {
        $this->authorize('delete', $issue);

        $result = $action->execute($issue);

        if (!$result->success) {
            return ApiResponse::error($result->message, $result->statusCode);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
