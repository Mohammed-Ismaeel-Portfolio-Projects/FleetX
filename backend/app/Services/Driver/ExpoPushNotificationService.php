<?php

namespace App\Services\Driver;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Expo Push HTTP provider (D12). FCM/APNs fan-out is performed by Expo.
 */
class ExpoPushNotificationService implements PushNotificationService
{
    /**
     * @param  list<string>  $tokens
     * @param  array{title: string, body: string, data: array<string, scalar|null>, priority?: string}  $message
     * @return list<array{token: string, ok: bool, invalid: bool, error: string|null}>
     */
    public function send(array $tokens, array $message): array
    {
        $tokens = array_values(array_unique(array_filter($tokens, fn ($t) => is_string($t) && $t !== '')));
        if ($tokens === []) {
            return [];
        }

        $url = (string) config('driver_push.expo.url');
        $timeout = max(1, (int) config('driver_push.expo.timeout', 8));
        $accessToken = config('driver_push.expo.access_token');

        $messages = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'title' => $message['title'],
                'body' => $message['body'],
                'sound' => 'default',
                'priority' => ($message['priority'] ?? 'default') === 'high' ? 'high' : 'default',
                'data' => $message['data'],
            ];
        }

        try {
            $pending = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->withHeaders(['Host' => 'exp.host']);

            if (is_string($accessToken) && $accessToken !== '') {
                $pending = $pending->withToken($accessToken);
            }

            $response = $pending->post($url, $messages);
        } catch (Throwable $e) {
            Log::warning('driver_push.expo_http_failed', ['error' => $e->getMessage()]);

            return array_map(fn (string $token) => [
                'token' => $token,
                'ok' => false,
                'invalid' => false,
                'error' => $e->getMessage(),
            ], $tokens);
        }

        if (! $response->successful()) {
            Log::warning('driver_push.expo_http_status', [
                'status' => $response->status(),
            ]);

            return array_map(fn (string $token) => [
                'token' => $token,
                'ok' => false,
                'invalid' => false,
                'error' => 'HTTP '.$response->status(),
            ], $tokens);
        }

        $payload = $response->json();
        $rows = $payload['data'] ?? null;
        if (! is_array($rows)) {
            return array_map(fn (string $token) => [
                'token' => $token,
                'ok' => false,
                'invalid' => false,
                'error' => 'Unexpected Expo response',
            ], $tokens);
        }

        if (isset($rows['status'])) {
            $rows = [$rows];
        }

        $out = [];
        foreach ($tokens as $i => $token) {
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            $status = $row['status'] ?? null;
            $error = is_string($row['details']['error'] ?? null)
                ? $row['details']['error']
                : (is_string($row['message'] ?? null) ? $row['message'] : null);
            $invalid = $error === 'DeviceNotRegistered';
            $out[] = [
                'token' => $token,
                'ok' => $status === 'ok',
                'invalid' => $invalid,
                'error' => $status === 'ok' ? null : ($error ?? 'expo_error'),
            ];
        }

        return $out;
    }
}
