<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Driver verification challenge TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Absolute lifetime from issuance. Default 60 minutes balances on-site
    | loading/unloading time against replay window. Override via
    | DRIVER_VERIFICATION_TTL_MINUTES.
    |
    */
    'ttl_minutes' => (int) env('DRIVER_VERIFICATION_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Opaque QR payload prefix
    |--------------------------------------------------------------------------
    */
    'qr_scheme' => 'fleetx://v1/verify',
];
