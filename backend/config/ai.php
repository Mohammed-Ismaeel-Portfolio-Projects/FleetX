<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FLeetX AI Subsystem Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for AI ETA Residual Correction, inference
    | timeouts, safety bounds, artifact locations, and telemetry options.
    |
    */

    'eta' => [
        'enabled' => env('AI_ETA_ENABLED', true),
        'timeout_seconds' => (float) env('AI_ETA_TIMEOUT', 15.0),
        'residual_min_bound' => (float) env('AI_ETA_RESIDUAL_MIN_BOUND', -43200.0), // -12 hours
        'residual_max_bound' => (float) env('AI_ETA_RESIDUAL_MAX_BOUND', 43200.0),  // +12 hours
        'script_path' => base_path('ai/eta/predict_residual.py'),
        'models_dir' => base_path('ai/eta/models'),
        'manifest_path' => base_path('ai/eta/models/feature_manifest.json'),
        'metadata_path' => base_path('ai/eta/models/model_metadata.json'),
        'logging_enabled' => env('AI_ETA_LOGGING', true),
    ],

];
