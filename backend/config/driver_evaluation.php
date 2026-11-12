<?php

declare(strict_types=1);

/**
 * DRIVER-EVAL-03 evaluation evidence detection configuration.
 *
 * Reuses driver_safety thresholds for speed/motion where possible.
 * Idle/stop/timing thresholds are explicit evaluation defaults (documented).
 */
return [
    'idle' => [
        // Align with UpdateTrackingStateAction Idle band (speed <= 0.5 ⇒ Idle).
        'max_speed_kmh' => (float) env('DRIVER_EVAL_IDLE_MAX_SPEED_KMH', 0.5),
        'min_duration_seconds' => (int) env('DRIVER_EVAL_IDLE_MIN_DURATION_SECONDS', 600),
        'min_samples' => (int) env('DRIVER_EVAL_IDLE_MIN_SAMPLES', 4),
        'max_gap_seconds' => (int) env('DRIVER_EVAL_IDLE_MAX_GAP_SECONDS', 120),
    ],

    'prolonged_stop' => [
        // Align with Stopped band (0.5 < speed <= 5).
        'min_speed_kmh' => (float) env('DRIVER_EVAL_STOP_MIN_SPEED_KMH', 0.5),
        'max_speed_kmh' => (float) env('DRIVER_EVAL_STOP_MAX_SPEED_KMH', 5.0),
        'min_duration_seconds' => (int) env('DRIVER_EVAL_PROLONGED_STOP_SECONDS', 900),
        'min_samples' => (int) env('DRIVER_EVAL_PROLONGED_STOP_MIN_SAMPLES', 4),
        'max_gap_seconds' => (int) env('DRIVER_EVAL_PROLONGED_STOP_MAX_GAP_SECONDS', 120),
    ],

    'harsh_acceleration' => [
        // Symmetric to motion harsh-brake defaults when not overridden.
        'ms2' => [
            'van' => (float) env('DRIVER_EVAL_HARSH_ACCEL_VAN_MS2', 2.5),
            'light_truck' => (float) env('DRIVER_EVAL_HARSH_ACCEL_LIGHT_MS2', 2.0),
            'heavy_truck' => (float) env('DRIVER_EVAL_HARSH_ACCEL_HEAVY_MS2', 1.5),
        ],
    ],

    'timing' => [
        // Route.estimated_time has no unit in schema; treated as minutes (documented assumption).
        'estimated_time_unit' => 'minutes',
        'abnormal_duration_ratio' => (float) env('DRIVER_EVAL_ABNORMAL_DURATION_RATIO', 1.5),
    ],

    'detectors' => [
        'speeding' => ['code' => 'eval.speeding', 'version' => '1.0.0'],
        'motion' => ['code' => 'eval.motion', 'version' => '1.0.0'],
        'idle' => ['code' => 'eval.idle', 'version' => '1.0.0'],
        'stop' => ['code' => 'eval.stop', 'version' => '1.0.0'],
        'route_deviation' => ['code' => 'eval.route_deviation', 'version' => '1.0.0'],
        'timing' => ['code' => 'eval.timing', 'version' => '1.0.0'],
        'workflow' => ['code' => 'eval.workflow', 'version' => '1.0.0'],
    ],

    /*
    | DRIVER-EVAL-04 explainable scoring defaults.
    | Product evaluation defaults — NOT legal or insurance standards.
    */
    'scoring' => [
        'version' => '1.0.0',
        'code' => 'eval.score',
        'baseline' => 100.0,
        'min_score' => 0.0,
        'max_score' => 100.0,
        'max_category_deduction' => 100.0,
        // Diminishing multiplier for repeated same event code within a category.
        'repetition_decay' => 0.7,
        'min_telemetry_samples' => 3,
        'labels' => [
            'excellent' => 90,
            'good' => 80,
            'acceptable' => 70,
            'needs_improvement' => 60,
        ],
        'categories' => [
            'safety' => 0.35,
            'driving_behavior' => 0.25,
            'route_compliance' => 0.15,
            'timeliness' => 0.15,
            'workflow_discipline' => 0.10,
        ],
        // Event code → scoring category (explicit; unsupported codes omitted).
        'event_category_map' => [
            'speeding' => 'safety',
            'harsh_braking' => 'safety',
            'sudden_stop' => 'safety',
            'harsh_acceleration' => 'driving_behavior',
            'excessive_idle' => 'driving_behavior',
            'prolonged_stop' => 'driving_behavior',
            'route_deviation' => 'route_compliance',
            'late_departure' => 'timeliness',
            'late_arrival' => 'timeliness',
            'abnormal_duration' => 'timeliness',
            'workflow_violation' => 'workflow_discipline',
        ],
        // Base impact points deducted for the first occurrence of each event code.
        'event_impacts' => [
            'speeding' => 12.0,
            'harsh_braking' => 10.0,
            'sudden_stop' => 14.0,
            'harsh_acceleration' => 8.0,
            'excessive_idle' => 6.0,
            'prolonged_stop' => 8.0,
            'route_deviation' => 15.0,
            'late_departure' => 8.0,
            'late_arrival' => 12.0,
            'abnormal_duration' => 10.0,
            'workflow_violation' => 20.0,
        ],
        /*
        | Overlap groups: when events share tracking-log evidence, keep only the
        | primary code at full impact; secondary codes in the group are skipped.
        */
        'overlap_groups' => [
            ['primary' => 'sudden_stop', 'secondary' => ['harsh_braking']],
            ['primary' => 'late_arrival', 'secondary' => ['abnormal_duration']],
        ],
    ],

    /*
    | DRIVER-EVAL-05 driver performance aggregation defaults.
    | Consumes finalized AssignmentEvaluation rows only — does not rescore.
    | Product defaults — NOT legal or insurance standards.
    */
    'aggregation' => [
        'version' => '1.0.0',
        'code' => 'eval.driver_agg',
        // Equal-weight mean of eligible assignment overall scores (+ explicit trend).
        // Confidence is a reporting dimension, not a numeric score weight.
        'strategy' => 'equal_weight_with_trend',
        'live_only' => true,
        'min_established_samples' => 5,
        'trend' => [
            'window_size' => 5,
            'improving_delta' => 3.0,
            'declining_delta' => -3.0,
        ],
        'labels' => [
            'excellent' => 90,
            'good' => 80,
            'acceptable' => 70,
            'needs_improvement' => 60,
        ],
    ],

    /*
    | DRIVER-EVAL-07 operational lifecycle integration.
    | Wiring only — does not change scoring/aggregation formulas.
    */
    'lifecycle' => [
        // Queued incremental detection after live tracking (throttled).
        'incremental_enabled' => (bool) env('DRIVER_EVAL_INCREMENTAL_ENABLED', true),
        'incremental_min_interval_seconds' => (int) env('DRIVER_EVAL_INCREMENTAL_INTERVAL_SECONDS', 120),
    ],
];
