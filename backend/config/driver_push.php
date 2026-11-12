<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver push (D12) — best-effort Expo delivery
    |--------------------------------------------------------------------------
    |
    | Inbox (driver_notifications) remains source of truth. When disabled,
    | devices can still register; no outbound Expo HTTP is sent.
    |
    | PHPUnit sets DRIVER_PUSH_ENABLED=false so D1–D11 suites never call Expo.
    |
    */

    'enabled' => filter_var(env('DRIVER_PUSH_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'provider' => env('DRIVER_PUSH_PROVIDER', 'expo'),

    'expo' => [
        'url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        'timeout' => (int) env('EXPO_PUSH_TIMEOUT', 8),
    ],

];
