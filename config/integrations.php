<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ścieżka do gniazda Docker Socket
    |--------------------------------------------------------------------------
    */
    'docker_socket' => env('DOCKER_SOCKET_PATH', '/var/run/docker.sock'),

    /*
    |--------------------------------------------------------------------------
    | Aktywny sterownik mock Docker
    |--------------------------------------------------------------------------
    | Gdy gniazdo /var/run/docker.sock nie jest dostępne lub włączono tryb mock,
    | DockerSocketService symuluje obecność środowiska kontenerowego.
    */
    'docker_mock' => (bool) env('DOCKER_MOCK', env('CONTAINERS_MOCK', false)),

    /*
    |--------------------------------------------------------------------------
    | Automatyczne wykrywanie kontenerów
    |--------------------------------------------------------------------------
    */
    'containers_discovery' => (bool) env('CONTAINERS_DISCOVERY', true),
];
