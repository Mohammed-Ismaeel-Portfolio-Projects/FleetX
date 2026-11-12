<?php

namespace App\Http\Controllers;

use App\Actions\Conversation\GetConversationAction;
use App\Actions\Conversation\ListMessagesAction;
use App\Actions\Conversation\MarkConversationReadAction;
use App\Actions\Conversation\OpenConversationAction;
use App\Actions\Conversation\QueryConversationsAction;
use App\Actions\Conversation\SendMessageAction;
use App\Core\ApiResponse;
use App\Http\Requests\Conversation\ListMessagesRequest;
use App\Http\Requests\Conversation\OpenConversationRequest;
use App\Http\Requests\Conversation\QueryConversationsRequest;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function index(QueryConversationsRequest $request, QueryConversationsAction $action): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        $result = $action->execute($request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function store(OpenConversationRequest $request, OpenConversationAction $action): JsonResponse
    {
        $this->authorize('create', Conversation::class);

        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();
        $user->loadMissing('role');

        $result = $action->execute($user, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        $status = str_contains(strtolower($result->message), 'created') ? 201 : 200;

        return ApiResponse::success(
            (new ConversationResource($result->data))->resolve(),
            $result->message,
            $status
        );
    }

    public function show(string $conversation, GetConversationAction $action): JsonResponse
    {
        $result = $action->execute($conversation);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        $this->authorize('view', $result->data);

        return ApiResponse::success(
            (new ConversationResource($result->data))->resolve(),
            $result->message
        );
    }

    public function messages(
        string $conversation,
        ListMessagesRequest $request,
        GetConversationAction $getAction,
        ListMessagesAction $listAction
    ): JsonResponse {
        $get = $getAction->execute($conversation);
        if (! $get->success) {
            return ApiResponse::error($get->message, $get->statusCode, $get->errors);
        }

        $this->authorize('view', $get->data);

        $result = $listAction->execute($get->data, $request->validated());

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }

    public function sendMessage(
        string $conversation,
        SendMessageRequest $request,
        GetConversationAction $getAction,
        SendMessageAction $sendAction
    ): JsonResponse {
        $get = $getAction->execute($conversation);
        if (! $get->success) {
            return ApiResponse::error($get->message, $get->statusCode, $get->errors);
        }

        $this->authorize('sendMessage', $get->data);

        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        $result = $sendAction->execute($user, $get->data, $request->validated('body'));

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success(
            (new MessageResource($result->data))->resolve(),
            $result->message,
            201
        );
    }

    public function markRead(
        string $conversation,
        GetConversationAction $getAction,
        MarkConversationReadAction $readAction
    ): JsonResponse {
        $get = $getAction->execute($conversation);
        if (! $get->success) {
            return ApiResponse::error($get->message, $get->statusCode, $get->errors);
        }

        $this->authorize('markRead', $get->data);

        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        $result = $readAction->execute($user, $get->data);

        if (! $result->success) {
            return ApiResponse::error($result->message, $result->statusCode, $result->errors);
        }

        return ApiResponse::success($result->data, $result->message);
    }
}
