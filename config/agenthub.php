<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Nazwa i wersja aplikacji
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Projekt-Emet'),
    'version' => file_exists(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : '1.7.0',
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
    | Brama modeli AI (LLM Gateway), Semantic Cache, Guardrails & Smart Routing
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'default_pool' => env('LLM_DEFAULT_POOL', 'default'),
        'default_cooldown_seconds' => (int) env('LLM_COOLDOWN_SECONDS', 60),
        'allow_prompt_logging' => (bool) env('ALLOW_PROMPT_LOGGING', true),
        'max_retry_attempts' => (int) env('LLM_MAX_RETRY_ATTEMPTS', 3),
        'request_timeout_seconds' => (int) env('LLM_REQUEST_TIMEOUT', 120),
        'semantic_cache' => [
            'enabled' => (bool) env('LLM_SEMANTIC_CACHE_ENABLED', false),
            'similarity_threshold' => (float) env('LLM_SEMANTIC_CACHE_THRESHOLD', 0.95),
            'ttl_seconds' => (int) env('LLM_SEMANTIC_CACHE_TTL', 86400),
            'max_temperature' => (float) env('LLM_SEMANTIC_CACHE_MAX_TEMP', 0.7),
        ],
        'guardrails' => [
            'enabled' => (bool) env('LLM_GUARDRAILS_ENABLED', true),
            'jailbreak_action' => env('LLM_GUARDRAILS_JAILBREAK_ACTION', 'block'),
            'dlp_enabled' => (bool) env('LLM_DLP_ENABLED', true),
            'dlp_restore_in_response' => (bool) env('LLM_DLP_RESTORE_RESPONSE', true),
            'dlp_skip_local_models' => (bool) env('LLM_DLP_SKIP_LOCAL_MODELS', true),
        ],
        'smart_routing' => [
            'low_threshold' => (float) env('LLM_SMART_ROUTING_LOW_THRESHOLD', 0.35),
            'high_threshold' => (float) env('LLM_SMART_ROUTING_HIGH_THRESHOLD', 0.65),
        ],
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
            'url' => env('QDRANT_URL'),
            'api_key' => env('QDRANT_API_KEY', null),
            'mock' => (bool) env('QDRANT_MOCK', false),
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
