<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * Conversation authorization — always via authenticated User identity.
 */
class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('conversation.viewAny');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->can('conversation.manage')) {
            return true;
        }

        return $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('conversation.create');
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        if ($user->can('conversation.manage')) {
            return true;
        }

        return $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function markRead(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
