<?php

namespace App\Services\Driver;

/**
 * Provider-agnostic Driver push send (D12).
 */
interface PushNotificationService
{
    /**
     * @param  list<string>  $tokens
     * @param  array{
     *     title: string,
     *     body: string,
     *     data: array<string, scalar|null>,
     *     priority?: string
     * }  $message
     * @return list<array{token: string, ok: bool, invalid: bool, error: string|null}>
     */
    public function send(array $tokens, array $message): array;
}
