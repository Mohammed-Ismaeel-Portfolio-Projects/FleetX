<?php

namespace App\Actions\Conversation;

use App\Actions\Notification\WriteClientNotificationAction;
use App\Core\ApiResult;
use App\Events\Conversation\MessageCreated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Support\ClientNotificationAction;
use App\Support\DriverNotificationAction;
use App\Support\DriverNotificationCode;
use App\Support\DriverNotificationWriter;
use Illuminate\Support\Facades\DB;

class SendMessageAction
{
    public function __construct(
        private readonly WriteClientNotificationAction $writeClientNotification
    ) {}

    public function execute(User $sender, Conversation $conversation, string $body): ApiResult
    {
        try {
            $body = trim($body);
            if ($body === '') {
                return ApiResult::validation(['body' => ['Message body is required.']]);
            }

            if ($conversation->status === Conversation::STATUS_CLOSED) {
                return ApiResult::error('Conversation is closed', 422);
            }

            // Ops users may join on first send when they have manage access.
            if (! $conversation->hasParticipant($sender)) {
                if (! $sender->can('conversation.manage')) {
                    return ApiResult::error('Forbidden', 403);
                }
                ConversationParticipant::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $sender->id,
                    ],
                    [
                        'participant_role' => ConversationParticipant::ROLE_DISPATCHER,
                    ]
                );
            }

            $message = DB::transaction(function () use ($sender, $conversation, $body) {
                $message = Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_user_id' => $sender->id,
                    'body' => $body,
                ]);

                $conversation->touch();

                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $sender->id)
                    ->update(['last_read_at' => now()]);

                return $message;
            });

            $message->load(['sender', 'conversation']);

            // Realtime delivery only after successful persistence (REST remains SSOT).
            // Broadcast failures must not fail the HTTP send (M8 Reverb disconnect).
            try {
                event(new MessageCreated($message));
            } catch (\Throwable $broadcastError) {
                report($broadcastError);
            }

            $this->notifyEligibleClientParticipants($sender, $conversation);
            $this->notifyEligibleDriverParticipants($sender, $conversation, $message);

            return ApiResult::success($message, 'Message sent');
        } catch (\Throwable $e) {
            return ApiResult::error('Failed to send message: '.$e->getMessage(), 500);
        }
    }

    /**
     * Inbox fan-out for dispatcher_customer threads only (C4.2-A2.4).
     * Recipients are conversation participants with role.name = client, excluding the sender.
     * Notification failure must never fail or roll back a persisted message.
     */
    private function notifyEligibleClientParticipants(User $sender, Conversation $conversation): void
    {
        try {
            if ($conversation->type !== Conversation::TYPE_DISPATCHER_CUSTOMER) {
                return;
            }

            $conversationId = $conversation->id;
            if (! is_string($conversationId) || $conversationId === '') {
                return;
            }

            $recipients = User::query()
                ->with('role')
                ->whereHas('role', fn ($q) => $q->where('name', 'client'))
                ->whereIn(
                    'id',
                    ConversationParticipant::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('user_id', '!=', $sender->id)
                        ->select('user_id')
                )
                ->orderBy('id')
                ->get();

            foreach ($recipients as $recipient) {
                $result = $this->writeClientNotification->execute([
                    'user_id' => $recipient->id,
                    'title' => 'New FleetX message',
                    'message' => 'You have a new message from FleetX Support.',
                    'type' => 'message.new',
                    'action_kind' => ClientNotificationAction::KIND_CONVERSATION,
                    'action_id' => $conversationId,
                ]);

                if (! $result->success) {
                    report(new \RuntimeException('Client message notification failed: '.$result->message));
                }
            }
        } catch (\Throwable $notificationError) {
            report($notificationError);
        }
    }

    /**
     * Driver inbox fan-out for dispatcher_driver threads (D8).
     * Never writes Client notifications. Never fails the message send.
     */
    private function notifyEligibleDriverParticipants(User $sender, Conversation $conversation, Message $message): void
    {
        try {
            if ($conversation->type !== Conversation::TYPE_DISPATCHER_DRIVER) {
                return;
            }

            $conversationId = $conversation->id;
            if (! is_string($conversationId) || $conversationId === '') {
                return;
            }

            $recipients = User::query()
                ->with(['role', 'driver'])
                ->whereHas('role', fn ($q) => $q->where('name', 'driver'))
                ->whereIn(
                    'id',
                    ConversationParticipant::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('user_id', '!=', $sender->id)
                        ->select('user_id')
                )
                ->orderBy('id')
                ->get();

            foreach ($recipients as $recipient) {
                $driver = $recipient->driver;
                if ($driver === null) {
                    continue;
                }

                DriverNotificationWriter::notify(
                    (int) $driver->id,
                    DriverNotificationCode::MESSAGE_UNREAD,
                    'New dispatcher message',
                    'You have a new message from your dispatcher.',
                    'info',
                    null,
                    DriverNotificationAction::KIND_CONVERSATION,
                    $conversationId,
                    DriverNotificationCode::MESSAGE_UNREAD.':'.$message->id
                );
            }
        } catch (\Throwable $notificationError) {
            report($notificationError);
        }
    }
}
