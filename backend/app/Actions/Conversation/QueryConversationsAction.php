<?php

namespace App\Actions\Conversation;

use App\Core\ApiResult;
use App\Core\Pagination;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class QueryConversationsAction
{
    public function execute(array $params = []): ApiResult
    {
        try {
            /** @var User $user */
            $user = Auth::guard('api')->user();
            $user->loadMissing('role');

            $query = Conversation::query()
                ->with(['shipment', 'participants.user'])
                ->orderByDesc('updated_at');

            if (! $user->can('conversation.manage')) {
                $query->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
            }

            if (! empty($params['type'])) {
                $query->where('type', $params['type']);
            }

            if (! empty($params['shipment_id'])) {
                $query->where('shipment_id', $params['shipment_id']);
            }

            if (! empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 15;
            $perPage = max(1, min($perPage, 50));

            $paginator = $query->paginate($perPage);

            $paginator->setCollection(
                $paginator->getCollection()->map(
                    fn (Conversation $c) => (new \App\Http\Resources\ConversationResource($c))->resolve()
                )
            );

            return ApiResult::success(Pagination::format($paginator), 'Conversations retrieved');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to retrieve conversations: '.$e->getMessage(), 500);
        }
    }
}
