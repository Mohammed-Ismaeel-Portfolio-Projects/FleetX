<?php

return [
    'lifecycle' => [
        'arrival_distance_m' => (float) env('SIM_LIFECYCLE_ARRIVAL_DISTANCE_M', 150),
        'arrival_speed_kmh' => (float) env('SIM_LIFECYCLE_ARRIVAL_SPEED_KMH', 8),
        'loading_duration_seconds' => (int) env('SIM_LIFECYCLE_LOADING_SECONDS', 180),
        'unloading_duration_seconds' => (int) env('SIM_LIFECYCLE_UNLOADING_SECONDS', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route intelligence / rest-stop policy (C2.9-C2.8 Step 5)
    |--------------------------------------------------------------------------
    | Deterministic demo-oriented driving limits — not legal HOS compliance.
    */
    'rest' => [
        'max_driving_distance_m' => (float) env('SIM_REST_MAX_DRIVING_DISTANCE_M', 80_000),
        'rest_duration_seconds' => (int) env('SIM_REST_DURATION_SECONDS', 900),
        'approach_distance_m' => (float) env('SIM_REST_APPROACH_DISTANCE_M', 1500),
        'geofence_radius_m' => (float) env('SIM_REST_GEOFENCE_RADIUS_M', 150),
        // Do not schedule a rest if remaining distance to delivery would be shorter than this.
        'min_remaining_after_rest_m' => (float) env('SIM_REST_MIN_REMAINING_AFTER_M', 20_000),
        'assumed_cruise_speed_kmh' => (float) env('SIM_REST_ASSUMED_CRUISE_SPEED_KMH', 80),
    ],

    'session' => [
        'default_tick_interval_seconds' => (int) env('SIM_SESSION_TICK_INTERVAL', 60),
        'default_time_scale' => (float) env('SIM_SESSION_TIME_SCALE', 1),
        'allowed_time_scales' => [1.0, 2.0, 5.0, 10.0],
    ],
];
