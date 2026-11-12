<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Secret
    |--------------------------------------------------------------------------
    |
    | Generated locally with: php artisan jwt:secret
    | Never commit the real value — keep it in .env only.
    |
    */

    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | JWT Authentication Keys
    |--------------------------------------------------------------------------
    |
    | Only used if you switch the algorithm below to an asymmetric one
    | (e.g. RS256). Not used with the default HS256 algorithm.
    |
    */

    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT time to live (Access Token)
    |--------------------------------------------------------------------------
    |
    | Phase 5 decision: 60 minutes.
    |
    */

    'ttl' => env('JWT_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Refresh time to live
    |--------------------------------------------------------------------------
    |
    | Phase 5 decision: 7 days (10080 minutes). This is the absolute window,
    | from original issuance, during which a token may still be refreshed.
    |
    */

    'refresh_ttl' => env('JWT_REFRESH_TTL', 10080),

    /*
    |--------------------------------------------------------------------------
    | JWT hashing algorithm
    |--------------------------------------------------------------------------
    |
    | Pinned explicitly to HS256 — never allow "none" or an unexpected
    | algorithm to be negotiated.
    |
    */

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Required Claims
    |--------------------------------------------------------------------------
    */

    'required_claims' => [
        'iss',
        'iat',
        'exp',
        'nbf',
        'sub',
        'jti',
    ],

    /*
    |--------------------------------------------------------------------------
    | Persistent Claims
    |--------------------------------------------------------------------------
    |
    | These claims are automatically added to every refreshed token, copied
    | over from the original token.
    |
    */

    'persistent_claims' => [],

    /*
    |--------------------------------------------------------------------------
    | Lock Subject
    |--------------------------------------------------------------------------
    |
    | Hashes the subject claim so it's not deterministic across guards.
    | Keep enabled for a single-model auth system like this one.
    |
    */

    'lock_subject' => true,

    /*
    |--------------------------------------------------------------------------
    | Leeway
    |--------------------------------------------------------------------------
    |
    | Allowed clock skew, in seconds, when validating exp/iat/nbf claims.
    |
    */

    'leeway' => env('JWT_LEEWAY', 0),

    /*
    |--------------------------------------------------------------------------
    | Blacklist Exception Enforcement
    |--------------------------------------------------------------------------
    |
    | When false, blacklisted tokens are still accepted (decode skips the
    | TokenBlacklistedException). Must stay true so logout/refresh
    | invalidation is enforced at the guard (C5.9 / Phase 5).
    |
    */

    'show_black_list_exception' => env('JWT_SHOW_BLACKLIST_EXCEPTION', true),

    /*
    |--------------------------------------------------------------------------
    | Blacklist Enabled
    |--------------------------------------------------------------------------
    |
    | Phase 5 decision: per-token logout via blacklist. Must stay true.
    |
    */

    'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Blacklist Grace Period
    |--------------------------------------------------------------------------
    |
    | Seconds of overlap allowed for an invalidated token (covers requests
    | already in flight when a refresh/logout happens). Kept tight.
    |
    */

    'blacklist_grace_period' => env('JWT_BLACKLIST_GRACE_PERIOD', 0),

    /*
    |--------------------------------------------------------------------------
    | Cookies encryption
    |--------------------------------------------------------------------------
    |
    | Not used — this API is header-token only, no cookie transport.
    |
    */

    'decrypt_cookies' => false,

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'jwt' => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,

        'auth' => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,

        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,

    ],

];
