<?php

namespace App\Events\Conversation;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a persisted message to the conversation's private channel.
 *
 * Dispatched ONLY after Message persistence succeeds.
 * Payload mirrors the MessageResource contract (safe client fields only).
 */
final class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['sender', 'conversation']);
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_user_id' => $this->message->sender_user_id,
            'body' => $this->message->body,
            'sender' => $sender ? [
                'id' => $sender->id,
                'name' => $sender->name,
                'email' => $sender->email,
            ] : null,
            'created_at' => $this->message->created_at?->toISOString(),
            'updated_at' => $this->message->updated_at?->toISOString(),
            'conversation_updated_at' => $this->message->conversation?->updated_at?->toISOString(),
        ];
    }
}
