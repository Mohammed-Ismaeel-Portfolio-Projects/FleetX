<?php

namespace App\Support;

/**
 * Client-safe notification action projection (C2.9-C3.8).
 *
 * Persistence is action_kind + action_id. The Client API only emits
 * { kind, id } for an allowlisted kind and a UUID id. Unknown kinds,
 * missing ids, and non-UUID ids become null — never guessed, never
 * leaked as internal operational identifiers.
 */
final class ClientNotificationAction
{
    public const KIND_SHIPMENT_DETAILS = 'shipment_details';

    public const KIND_SHIPMENT_PROGRESS = 'shipment_progress';

    public const KIND_REQUEST_DETAILS = 'request_details';

    public const KIND_CONVERSATION = 'conversation';

    /**
     * @var list<string>
     */
    public const ALLOWED_KINDS = [
        self::KIND_SHIPMENT_DETAILS,
        self::KIND_SHIPMENT_PROGRESS,
        self::KIND_REQUEST_DETAILS,
        self::KIND_CONVERSATION,
    ];

    /**
     * @return array{kind: string, id: string}|null
     */
    public static function toClientPayload(?string $kind, mixed $id): ?array
    {
        $safeKind = is_string($kind) ? trim($kind) : '';
        $safeId = is_string($id) ? trim($id) : '';

        if ($safeKind === '' || $safeId === '') {
            return null;
        }

        if (! in_array($safeKind, self::ALLOWED_KINDS, true)) {
            return null;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $safeId)) {
            return null;
        }

        return [
            'kind' => $safeKind,
            'id' => $safeId,
        ];
    }
}
