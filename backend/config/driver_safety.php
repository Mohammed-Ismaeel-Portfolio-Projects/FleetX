<?php

return [
    /*
    | Driver speed safety (D14).
    | Detects configured-threshold excess from validated device km/h.
    | This is NOT legal road-speed enforcement.
    */
    'speed' => [
        'enabled' => filter_var(env('DRIVER_SAFETY_SPEED_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'threshold_kmh' => (float) env('DRIVER_SAFETY_SPEED_THRESHOLD_KMH', 110),
        'min_samples' => (int) env('DRIVER_SAFETY_SPEED_MIN_SAMPLES', 3),
        'min_duration_seconds' => (int) env('DRIVER_SAFETY_SPEED_MIN_DURATION_SECONDS', 10),
        'cooldown_seconds' => (int) env('DRIVER_SAFETY_SPEED_COOLDOWN_SECONDS', 300),
        'recovery_kmh' => (float) env('DRIVER_SAFETY_SPEED_RECOVERY_KMH', 95),
        'max_accuracy_meters' => (float) env('DRIVER_SAFETY_SPEED_MAX_ACCURACY_METERS', 50),
        'max_sample_age_seconds' => (int) env('DRIVER_SAFETY_SPEED_MAX_SAMPLE_AGE_SECONDS', 30),
        'max_plausible_kmh' => (float) env('DRIVER_SAFETY_SPEED_MAX_PLAUSIBLE_KMH', 200),
        'lookback_seconds' => (int) env('DRIVER_SAFETY_SPEED_LOOKBACK_SECONDS', 120),
    ],

    /*
    | Driver live motion safety (D15-B).
    | Observes consecutive TrackingLog.speed (km/h) as m/s².
    | NOT simulation 60-second-tick accel/brake ramps.
    | NOT legal brake regulation. Evaluated by EvaluateDriverMotionSafetyAction (D15-C).
    */
    'motion' => [
        'enabled' => filter_var(env('DRIVER_SAFETY_MOTION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'min_samples' => (int) env('DRIVER_SAFETY_MOTION_MIN_SAMPLES', 3),
        'min_duration_seconds' => (int) env('DRIVER_SAFETY_MOTION_MIN_DURATION_SECONDS', 10),
        'cooldown_seconds' => (int) env('DRIVER_SAFETY_MOTION_COOLDOWN_SECONDS', 300),
        'min_interval_seconds' => (float) env('DRIVER_SAFETY_MOTION_MIN_INTERVAL_SECONDS', 3),
        'max_interval_seconds' => (float) env('DRIVER_SAFETY_MOTION_MAX_INTERVAL_SECONDS', 15),
        'max_plausible_deceleration_ms2' => (float) env('DRIVER_SAFETY_MOTION_MAX_PLAUSIBLE_DECEL_MS2', 8.0),
        'max_accuracy_meters' => (float) env('DRIVER_SAFETY_MOTION_MAX_ACCURACY_METERS', 50),
        'max_sample_age_seconds' => (int) env('DRIVER_SAFETY_MOTION_MAX_SAMPLE_AGE_SECONDS', 30),
        'lookback_seconds' => (int) env('DRIVER_SAFETY_MOTION_LOOKBACK_SECONDS', 120),
        'recovery_deceleration_ms2' => (float) env('DRIVER_SAFETY_MOTION_RECOVERY_DECEL_MS2', 0.5),
        'harsh_brake_ms2' => [
            'van' => (float) env('DRIVER_SAFETY_MOTION_HARSH_BRAKE_VAN_MS2', 2.5),
            'light_truck' => (float) env('DRIVER_SAFETY_MOTION_HARSH_BRAKE_LIGHT_MS2', 2.0),
            'heavy_truck' => (float) env('DRIVER_SAFETY_MOTION_HARSH_BRAKE_HEAVY_MS2', 1.5),
        ],
        'sudden_stop' => [
            'moving_min_kmh' => (float) env('DRIVER_SAFETY_MOTION_STOP_MOVING_MIN_KMH', 30),
            'stopped_max_kmh' => (float) env('DRIVER_SAFETY_MOTION_STOP_STOPPED_MAX_KMH', 5),
            'min_speed_drop_kmh' => (float) env('DRIVER_SAFETY_MOTION_STOP_MIN_DROP_KMH', 25),
        ],
    ],
];
