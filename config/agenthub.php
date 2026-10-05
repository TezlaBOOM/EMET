<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Nazwa i wersja aplikacji
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Projekt-Emet'),
    'version' => '1.0.0',
    'setup_completed' => (bool) env('SETUP_COMPLETED', false),

    /*
    |--------------------------------------------------------------------------
    | Tryb działania mikroserwisów (local / remote)
    |--------------------------------------------------------------------------
    */
    'service_mode' => env('SERVICE_MODE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Zarządzanie instancjami agentów (Hermes Agent, OpenClaw)
    |--------------------------------------------------------------------------
    */
    'instances' => [
        'data_dir' => env('INSTANCE_DATA_DIR', storage_path('instances')),
        'config_dir' => env('INSTANCE_CONFIG_DIR', storage_path('instances/configs')),
        'port_min' => (int) env('INSTANCE_PORT_MIN', 9000),
        'port_max' => (int) env('INSTANCE_PORT_MAX', 9999),
        'max_instances' => (int) env('INSTANCE_MAX', 20),
        'reconcile_interval_seconds' => (int) env('INSTANCE_RECONCILE_INTERVAL', 30),
        'health_check_timeout' => (int) env('INSTANCE_HEALTH_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Brama modeli AI (LLM Gateway)
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'default_pool' => env('LLM_DEFAULT_POOL', 'default'),
        'default_cooldown_seconds' => (int) env('LLM_COOLDOWN_SECONDS', 60),
        'allow_prompt_logging' => (bool) env('ALLOW_PROMPT_LOGGING', true),
        'max_retry_attempts' => (int) env('LLM_MAX_RETRY_ATTEMPTS', 3),
        'request_timeout_seconds' => (int) env('LLM_REQUEST_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Magazyn Pamięci Wektorowej (Vector Store)
    |--------------------------------------------------------------------------
    */
    'memory' => [
        'default_driver' => env('VECTOR_STORE_DRIVER', 'qdrant'),
        'qdrant' => [
            'host' => env('QDRANT_HOST', '127.0.0.1'),
            'port' => (int) env('QDRANT_PORT', 6333),
            'api_key' => env('QDRANT_API_KEY', null),
        ],
        'similarity_threshold' => (float) env('VECTOR_SIMILARITY_THRESHOLD', 0.70),
        'default_dimensions' => (int) env('VECTOR_DEFAULT_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ścieżka do skryptów systemowych i aktualizacji
    |--------------------------------------------------------------------------
    */
    'scripts' => [
        'path' => base_path('scripts'),
    ],
];
