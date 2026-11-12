<?php

namespace App\Actions\Conversation;

use App\Core\ApiResult;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;

class MarkConversationReadAction
{
    public function execute(User $user, Conversation $conversation): ApiResult
    {
        try {
            if (! $conversation->hasParticipant($user)) {
                if (! $user->can('conversation.manage')) {
                    return ApiResult::error('Forbidden', 403);
                }

                ConversationParticipant::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'participant_role' => ConversationParticipant::ROLE_DISPATCHER,
                    ]
                );
            }

            $participant = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $participant) {
                return ApiResult::error('Participant record unavailable', 422);
            }

            $participant->last_read_at = now();
            $participant->save();

            return ApiResult::success([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'last_read_at' => $participant->last_read_at?->toISOString(),
            ], 'Conversation marked read');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to mark conversation read: '.$e->getMessage(), 500);
        }
    }
}
