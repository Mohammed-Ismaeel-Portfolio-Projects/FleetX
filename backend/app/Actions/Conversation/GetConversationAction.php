<?php

namespace App\Actions\Conversation;

use App\Core\ApiResult;
use App\Models\Conversation;

class GetConversationAction
{
    public function execute(string $conversationId): ApiResult
    {
        $conversation = Conversation::query()
            ->with(['shipment', 'participants.user', 'assignment'])
            ->find($conversationId);

        if (! $conversation) {
            return ApiResult::notFound('Conversation not found');
        }

        return ApiResult::success($conversation, 'Conversation retrieved');
    }
}
