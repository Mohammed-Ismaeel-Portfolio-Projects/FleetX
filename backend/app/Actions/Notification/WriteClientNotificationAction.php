<?php

namespace App\Actions\Notification;

use App\Core\ApiResult;
use App\Models\Notification;
use App\Models\User;
use App\Support\ClientNotificationAction;

/**
 * Persist one Client inbox notification (C4.2-A2.1).
 *
 * Fail-closed: allowlisted action_kind + UUID action_id via ClientNotificationAction.
 * Recipient is a server-supplied user id — never Client HTTP input.
 * Title/message are informational only and are never used for routing.
 */
class WriteClientNotificationAction
{
    /**
     * Client-supported `type` values. Do not introduce new types here.
     *
     * @var list<string>
     */
    public const ALLOWED_TYPES = [
        'shipment.status',
        'message.new',
    ];

    /**
     * @param  array{
     *     user_id?: mixed,
     *     title?: mixed,
     *     message?: mixed,
     *     type?: mixed,
     *     action_kind?: mixed,
     *     action_id?: mixed
     * }  $data
     */
    public function execute(array $data): ApiResult
    {
        $userId = $this->normalizeUserId($data['user_id'] ?? null);
        $title = is_string($data['title'] ?? null) ? trim($data['title']) : '';
        $message = is_string($data['message'] ?? null) ? trim($data['message']) : '';
        $type = is_string($data['type'] ?? null) ? trim($data['type']) : '';
        $kind = $data['action_kind'] ?? null;
        $actionId = $data['action_id'] ?? null;

        $errors = [];

        if ($userId === null) {
            $errors['user_id'] = ['A server-side recipient user is required.'];
        }

        if ($title === '') {
            $errors['title'] = ['Title is required.'];
        }

        if ($message === '') {
            $errors['message'] = ['Message is required.'];
        }

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $errors['type'] = ['Type must be a Client-supported notification type.'];
        }

        $kindError = $this->actionKindError($kind);
        if ($kindError !== null) {
            $errors['action_kind'] = [$kindError];
        }

        $idError = $this->actionIdError($actionId);
        if ($idError !== null) {
            $errors['action_id'] = [$idError];
        }

        $payload = ClientNotificationAction::toClientPayload(
            is_string($kind) ? $kind : null,
            $actionId
        );

        if ($payload === null && ! isset($errors['action_kind']) && ! isset($errors['action_id'])) {
            $errors['action'] = ['action_kind and action_id must be an allowlisted kind and a UUID.'];
        }

        if ($errors !== []) {
            return ApiResult::validation($errors, 'Invalid Client notification');
        }

        /** @var array{kind: string, id: string} $payload */
        $user = User::query()->with('role')->find($userId);

        if ($user === null || $user->role?->name !== 'client') {
            return ApiResult::validation(
                ['user_id' => ['Recipient must be a Client user.']],
                'Invalid Client notification'
            );
        }

        try {
            $notification = Notification::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'is_read' => false,
                'action_kind' => $payload['kind'],
                'action_id' => $payload['id'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return ApiResult::error('Failed to persist Client notification', 500);
        }

        return ApiResult::success($notification, 'Client notification written');
    }

    private function normalizeUserId(mixed $userId): ?int
    {
        if (is_int($userId) && $userId > 0) {
            return $userId;
        }

        if (is_string($userId) && ctype_digit($userId) && (int) $userId > 0) {
            return (int) $userId;
        }

        return null;
    }

    private function actionKindError(mixed $kind): ?string
    {
        $safeKind = is_string($kind) ? trim($kind) : '';

        if ($safeKind === '') {
            return 'Action kind is required.';
        }

        if (! in_array($safeKind, ClientNotificationAction::ALLOWED_KINDS, true)) {
            return 'Action kind is not allowlisted.';
        }

        return null;
    }

    private function actionIdError(mixed $actionId): ?string
    {
        if ($actionId === null) {
            return 'Action id is required.';
        }

        if (! is_string($actionId)) {
            return 'Action id must be a UUID.';
        }

        $safeId = trim($actionId);

        if ($safeId === '') {
            return 'Action id is required.';
        }

        $payload = ClientNotificationAction::toClientPayload(
            ClientNotificationAction::KIND_SHIPMENT_DETAILS,
            $safeId
        );

        if ($payload === null) {
            return 'Action id must be a UUID.';
        }

        return null;
    }
}
