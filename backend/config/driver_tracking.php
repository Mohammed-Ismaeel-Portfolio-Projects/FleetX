<?php

return [
    /*
    | Driver foreground telemetry (D6).
    */
    'min_interval_seconds' => (int) env('DRIVER_TRACKING_MIN_INTERVAL_SECONDS', 5),
    'min_distance_meters' => (float) env('DRIVER_TRACKING_MIN_DISTANCE_METERS', 25),
    'max_accuracy_meters' => (float) env('DRIVER_TRACKING_MAX_ACCURACY_METERS', 200),
    'recorded_at_max_past_seconds' => (int) env('DRIVER_TRACKING_MAX_PAST_SECONDS', 3600),
    'recorded_at_max_future_seconds' => (int) env('DRIVER_TRACKING_MAX_FUTURE_SECONDS', 120),
    'dedupe_window_seconds' => (int) env('DRIVER_TRACKING_DEDUPE_WINDOW_SECONDS', 2),
    'fresh_below_seconds' => 300,
    'stale_max_seconds' => 1800,
];
