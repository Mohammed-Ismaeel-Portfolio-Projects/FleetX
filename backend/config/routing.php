<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Routing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for routing providers and their settings.
    |
    */

    'osrm' => [
        'url' => env('OSRM_URL', 'http://localhost:5000'),
        'profile' => env('OSRM_PROFILE', 'driving'),
        'timeout' => env('OSRM_TIMEOUT', 30),
        'retry_attempts' => env('OSRM_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('OSRM_RETRY_DELAY', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphHopper Directions API
    |--------------------------------------------------------------------------
    */
    'graphhopper' => [
        'url' => env('GRAPH_HOPPER_URL', 'https://graphhopper.com/api/1'),
        'api_key' => env('GRAPH_HOPPER_API_KEY'),
        'profile' => env('GRAPH_HOPPER_PROFILE', 'truck'),
        'vehicle_type' => env('GRAPH_HOPPER_VEHICLE_TYPE', 'heavy_truck'),
        'enable_truck_restrictions' => env('GRAPH_HOPPER_TRUCK_RESTRICTIONS', true),
        /*
         * Flexible Routing (custom_model + ch.disable + truck profiles).
         * Free GraphHopper packages must leave this false (classic car requests only).
         * Paid accounts: set GRAPH_HOPPER_ENABLE_FLEXIBLE_ROUTING=true for truck-aware routing.
         */
        'enable_flexible_routing' => env('GRAPH_HOPPER_ENABLE_FLEXIBLE_ROUTING', false),
        'timeout' => env('GRAPH_HOPPER_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Selection
    |--------------------------------------------------------------------------
    |
    | Options: 'graphhopper', 'osrm', 'simulator'
    |
    */
    'default_provider' => env('ROUTING_PROVIDER', 'graphhopper'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | Fall back to simulator when GraphHopper is unavailable or request fails.
    |
    */
    'fallback_enabled' => env('ROUTING_FALLBACK_ENABLED', true),
    'fallback_provider' => env('ROUTING_FALLBACK_PROVIDER', 'simulator'),

    /*
    |--------------------------------------------------------------------------
    | Provider Priority
    |--------------------------------------------------------------------------
    |
    | Higher = preferred when using automatic selection.
    |
    */
    'provider_priority' => [
        'graphhopper' => 200,
        'osrm' => 150,
        'simulator' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Deviation Detection & Smart Rerouting
    |--------------------------------------------------------------------------
    */
    'deviation' => [
        'enabled' => env('ROUTE_DEVIATION_ENABLED', true),
        'threshold_meters' => (float) env('ROUTE_DEVIATION_THRESHOLD_METERS', 100),
        'auto_reroute' => env('AUTO_REROUTE_ENABLED', true),
        'cache_ttl_seconds' => (int) env('ROUTE_CACHE_TTL_SECONDS', 21600),
        'reroute_cooldown_seconds' => (int) env('ROUTE_REROUTE_COOLDOWN_SECONDS', 120),
    ],
];
