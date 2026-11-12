<?php

namespace App\Actions\Conversation;

use App\Core\ApiResult;
use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Find-or-create a shipment-scoped conversation with legitimate User participants only.
 *
 * Creation (new thread) is dispatcher/admin only.
 * Driver/client may open/join an existing thread they are authorized for.
 */
class OpenConversationAction
{
    public function execute(User $actor, array $data): ApiResult
    {
        try {
            $type = $data['type'];
            $shipmentId = $data['shipment_id'];

            $shipment = Shipment::query()
                ->with(['activeAssignment.driver.user.role', 'customerCompany'])
                ->find($shipmentId);

            if (! $shipment) {
                return ApiResult::notFound('Shipment not found');
            }

            $existing = Conversation::query()
                ->where('type', $type)
                ->where('shipment_id', $shipmentId)
                ->first();

            if ($existing) {
                if (! $this->actorMayAccess($actor, $existing, $shipment)) {
                    return ApiResult::error('Forbidden', 403);
                }

                $this->ensureActorParticipant($actor, $existing, $shipment);
                $existing->load(['shipment', 'participants.user']);

                return ApiResult::success($existing, 'Conversation opened');
            }

            if (! $actor->can('conversation.manage')) {
                return ApiResult::error(
                    'Conversation does not exist yet. A dispatcher must open this thread first.',
                    404
                );
            }

            $resolution = $this->resolveCounterpart($type, $shipment, $data);
            if (! $resolution['ok']) {
                return ApiResult::error($resolution['message'], $resolution['status']);
            }

            /** @var User $counterpart */
            $counterpart = $resolution['counterpart'];
            $counterpartRole = $resolution['counterpart_role'];
            $assignmentId = $resolution['assignment_id'];

            $conversation = DB::transaction(function () use ($actor, $type, $shipment, $counterpart, $counterpartRole, $assignmentId) {
                $conversation = Conversation::create([
                    'type' => $type,
                    'shipment_id' => $shipment->id,
                    'assignment_id' => $assignmentId,
                    'status' => Conversation::STATUS_OPEN,
                    'created_by' => $actor->id,
                ]);

                $this->addParticipant($conversation, $actor, ConversationParticipant::ROLE_DISPATCHER);
                $this->addParticipant($conversation, $counterpart, $counterpartRole);

                return $conversation;
            });

            $conversation->load(['shipment', 'participants.user']);

            return ApiResult::success($conversation, 'Conversation created');
        } catch (\Throwable $e) {
            if ($this->isUniqueViolation($e)) {
                $existing = Conversation::query()
                    ->where('type', $data['type'])
                    ->where('shipment_id', $data['shipment_id'])
                    ->first();
                if ($existing && $this->actorMayAccess($actor, $existing, $existing->shipment ?? Shipment::find($data['shipment_id']))) {
                    $this->ensureActorParticipant($actor, $existing, $existing->shipment ?? Shipment::find($data['shipment_id']));
                    $existing->load(['shipment', 'participants.user']);

                    return ApiResult::success($existing, 'Conversation opened');
                }
            }

            return ApiResult::error('Failed to open conversation: '.$e->getMessage(), 500);
        }
    }

    /**
     * @return array{ok:bool,message?:string,status?:int,counterpart?:User,counterpart_role?:string,assignment_id?:int|null}
     */
    private function resolveCounterpart(string $type, Shipment $shipment, array $data): array
    {
        if ($type === Conversation::TYPE_DISPATCHER_DRIVER) {
            $assignmentId = null;
            $driverUser = null;

            if (! empty($data['driver_user_id'])) {
                $driverUser = User::query()->with(['role', 'driver'])->find($data['driver_user_id']);
            } else {
                $assignment = $shipment->activeAssignment;
                $driverUser = $assignment?->driver?->user;
                $assignmentId = $assignment?->id;
            }

            if (! $driverUser || $driverUser->role?->name !== 'driver' || ! $driverUser->driver) {
                return ['ok' => false, 'message' => 'Shipment has no linked Driver user for messaging', 'status' => 422];
            }

            if ($assignmentId === null) {
                $assignment = Assignment::query()
                    ->where('shipment_id', $shipment->id)
                    ->where('driver_id', $driverUser->driver->id)
                    ->where('is_active', true)
                    ->first();
                $assignmentId = $assignment?->id;
            }

            if (! $assignmentId) {
                return ['ok' => false, 'message' => 'Driver is not on an active assignment for this shipment', 'status' => 422];
            }

            return [
                'ok' => true,
                'counterpart' => $driverUser,
                'counterpart_role' => ConversationParticipant::ROLE_DRIVER,
                'assignment_id' => $assignmentId,
            ];
        }

        if ($type === Conversation::TYPE_DISPATCHER_CUSTOMER) {
            if (empty($shipment->customer_company_id)) {
                return ['ok' => false, 'message' => 'Shipment has no customer company', 'status' => 422];
            }

            if (! empty($data['client_user_id'])) {
                $clientUser = User::query()->with(['role', 'company'])->find($data['client_user_id']);
            } else {
                $clientUser = User::query()
                    ->with(['role', 'company'])
                    ->where('company_id', $shipment->customer_company_id)
                    ->whereHas('role', fn ($q) => $q->where('name', 'client'))
                    ->orderBy('id')
                    ->first();
            }

            if (
                ! $clientUser
                || $clientUser->role?->name !== 'client'
                || (string) $clientUser->company_id !== (string) $shipment->customer_company_id
            ) {
                return ['ok' => false, 'message' => 'No linked Client user for this shipment company', 'status' => 422];
            }

            return [
                'ok' => true,
                'counterpart' => $clientUser,
                'counterpart_role' => ConversationParticipant::ROLE_CLIENT,
                'assignment_id' => $shipment->activeAssignment?->id,
            ];
        }

        return ['ok' => false, 'message' => 'Unsupported conversation type', 'status' => 422];
    }

    private function actorMayAccess(User $actor, Conversation $conversation, ?Shipment $shipment): bool
    {
        if ($actor->can('conversation.manage')) {
            return true;
        }

        if ($conversation->hasParticipant($actor)) {
            return true;
        }

        if (! $shipment) {
            return false;
        }

        $role = $actor->role?->name;
        if ($role === 'driver' && $conversation->type === Conversation::TYPE_DISPATCHER_DRIVER) {
            $actor->loadMissing('driver');

            return (bool) ($actor->driver && Assignment::query()
                ->where('shipment_id', $shipment->id)
                ->where('driver_id', $actor->driver->id)
                ->where('is_active', true)
                ->exists());
        }

        if ($role === 'client' && $conversation->type === Conversation::TYPE_DISPATCHER_CUSTOMER) {
            return (bool) ($actor->company_id
                && (string) $actor->company_id === (string) $shipment->customer_company_id);
        }

        return false;
    }

    private function ensureActorParticipant(User $actor, Conversation $conversation, ?Shipment $shipment): void
    {
        if ($conversation->hasParticipant($actor)) {
            return;
        }

        if (! $this->actorMayAccess($actor, $conversation, $shipment)) {
            return;
        }

        $role = match ($actor->role?->name) {
            'driver' => ConversationParticipant::ROLE_DRIVER,
            'client' => ConversationParticipant::ROLE_CLIENT,
            default => ConversationParticipant::ROLE_DISPATCHER,
        };

        $this->addParticipant($conversation, $actor, $role);
    }

    private function addParticipant(Conversation $conversation, User $user, string $participantRole): void
    {
        ConversationParticipant::query()->firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'participant_role' => $participantRole,
            ]
        );
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'conversations_type_shipment_unique')
            || str_contains($message, 'duplicate')
            || (str_contains($message, 'unique') && str_contains($message, 'conversation'));
    }
}
