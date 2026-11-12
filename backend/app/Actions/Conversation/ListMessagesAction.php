<?php

namespace App\Actions\Conversation;

use App\Core\ApiResult;
use App\Core\Pagination;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;

class ListMessagesAction
{
    public function execute(Conversation $conversation, array $params = []): ApiResult
    {
        try {
            $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 30;
            $perPage = max(1, min($perPage, 100));

            $paginator = Message::query()
                ->where('conversation_id', $conversation->id)
                ->with('sender')
                ->orderBy('created_at')
                ->orderBy('id')
                ->paginate($perPage);

            $paginator->setCollection(
                $paginator->getCollection()->map(
                    fn (Message $m) => (new MessageResource($m))->resolve()
                )
            );

            return ApiResult::success(Pagination::format($paginator), 'Messages retrieved');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to retrieve messages: '.$e->getMessage(), 500);
        }
    }
}
