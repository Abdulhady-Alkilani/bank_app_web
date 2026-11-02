<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI scoring service
    |--------------------------------------------------------------------------
    | driver: "fake" (in-process deterministic scorer), "http" (FastAPI service)
    | or "none" (AI disabled: rules-only fallback).
    */
    'ai' => [
        'driver' => env('AI_DRIVER', 'fake'),
        'url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),
        'token' => env('AI_SERVICE_TOKEN'),
        'timeout_ms' => (int) env('AI_TIMEOUT_MS', 250),
        // Shadow mode: score every event with the shadow model too (queued, never affects the decision).
        'shadow_enabled' => (bool) env('AI_SHADOW_ENABLED', false),
        'shadow_timeout_seconds' => 2,
        // Bearer token for POST {url}/admin/reload (champion sync after promotion).
        'admin_token' => env('AI_ADMIN_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature cache
    |--------------------------------------------------------------------------
    | Cache store used for the real-time counters (null = default store).
    */
    'feature_cache_store' => env('FRAUD_FEATURE_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Bank webhook (outbound action commands: block / freeze / require_otp)
    |--------------------------------------------------------------------------
    | Empty URL = commands are only logged. Requests are signed with HMAC-SHA256
    | (X-Timestamp + X-Signature = hmac(secret, timestamp.body)).
    */
    'bank_webhook_url' => env('BANK_WEBHOOK_URL'),
    'bank_webhook_secret' => env('BANK_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | External API
    |--------------------------------------------------------------------------
    */
    'api_rate_limit' => (int) env('FRAUD_API_RATE_LIMIT', 600),
    'hmac_tolerance_seconds' => (int) env('FRAUD_HMAC_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | ML service ingest token (Bearer) for POST /api/v1/ml/model-runs
    |--------------------------------------------------------------------------
    */
    'ml_ingest_token' => env('ML_INGEST_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | MCP local (stdio) server: staff user the session acts as (no HTTP token there)
    |--------------------------------------------------------------------------
    */
    'mcp_local_user_email' => env('MCP_LOCAL_USER_EMAIL'),

];
