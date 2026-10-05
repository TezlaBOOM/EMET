<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Minimalne wymagania środowiska
    |--------------------------------------------------------------------------
    */
    'requirements' => [
        'min_php_version' => '8.3.0',
        'required_extensions' => [
            'bcmath',
            'curl',
            'mbstring',
            'openssl',
            'pdo',
            'tokenizer',
            'xml',
            'intl',
            'json',
        ],
        'min_disk_free_mb' => 500,
        'writable_paths' => [
            'storage',
            'storage/app',
            'storage/framework',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Macierz kompatybilności adapterów runtime'ów ze środowiskiem hosta
    |--------------------------------------------------------------------------
    */
    'adapters' => [
        'hermes' => [
            'min_version' => '1.0.0',
            'supported_modes' => ['systemd', 'docker'],
        ],
        'openclaw' => [
            'min_version' => '2.0.0',
            'supported_modes' => ['systemd', 'docker'],
        ],
        'claude_code' => [
            'min_version' => '0.1.0',
            'supported_modes' => ['systemd'],
        ],
        'codex' => [
            'min_version' => '1.0.0',
            'supported_modes' => ['docker'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kanały aktualizacji
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'stable' => [
            'url' => 'https://api.agenthub.lan/updates/stable.json',
        ],
        'beta' => [
            'url' => 'https://api.agenthub.lan/updates/beta.json',
        ],
    ],
];
