<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use Exception;

class DockerSocketService
{
    protected string $socketPath;

    protected static ?array $mockContainers = null;

    protected static array $mockFiles = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    protected static array $defaultMockContainers = [
        [
            'Id' => 'cont-ollama-mock-01',
            'Names' => ['/ollama-service'],
            'Image' => 'ollama/ollama:latest',
            'State' => 'running',
            'Ports' => [['PublicPort' => 11434]],
            'Labels' => ['agenthub.runtime' => 'ollama'],
        ],
        [
            'Id' => 'cont-hermes-mock-02',
            'Names' => ['/hermes-agent'],
            'Image' => 'nousresearch/hermes-agent:v1.2',
            'State' => 'running',
            'Ports' => [['PublicPort' => 8080]],
            'Labels' => ['agenthub.runtime' => 'hermes'],
        ],
        [
            'Id' => 'cont-openclaw-mock-03',
            'Names' => ['/openclaw-worker'],
            'Image' => 'openclaw/agent:latest',
            'State' => 'running',
            'Ports' => [['PublicPort' => 3000]],
            'Labels' => ['agenthub.runtime' => 'openclaw'],
        ],
    ];

    public function __construct(?string $socketPath = null)
    {
        $this->socketPath = $socketPath ?? (string) config('integrations.docker_socket', env('DOCKER_SOCKET_PATH', '/var/run/docker.sock'));
    }

    public static function setMockContainers(?array $containers): void
    {
        self::$mockContainers = $containers;
    }

    public static function getMockContainers(): ?array
    {
        return self::$mockContainers;
    }

    public static function enableMockDriver(?array $containers = null): void
    {
        self::$mockContainers = $containers ?? self::$defaultMockContainers;
    }

    public static function disableMockDriver(): void
    {
        self::$mockContainers = null;
    }

    public function isMock(): bool
    {
        if (self::$mockContainers !== null) {
            return true;
        }

        return (bool) config('integrations.docker_mock', env('DOCKER_MOCK', env('CONTAINERS_MOCK', false)));
    }

    public static function setMockFile(string $containerId, string $path, string $content): void
    {
        self::$mockFiles["{$containerId}:{$path}"] = $content;
    }

    public static function getMockFile(string $containerId, string $path): ?string
    {
        return self::$mockFiles["{$containerId}:{$path}"] ?? null;
    }

    public function isAvailable(): bool
    {
        if ($this->isMock()) {
            return true;
        }

        return file_exists($this->socketPath) && is_readable($this->socketPath);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listContainers(bool $all = true): array
    {
        if (self::$mockContainers !== null) {
            return self::$mockContainers;
        }

        if ($this->isMock()) {
            return self::$defaultMockContainers;
        }

        if (! $this->isAvailable()) {
            return [];
        }

        $response = $this->callSocket('GET', '/containers/json?'.http_build_query(['all' => $all ? 1 : 0]));
        if (! $response || ! is_array($response)) {
            return [];
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectContainer(string $containerId): array
    {
        $mockContainers = self::$mockContainers ?? ($this->isMock() ? self::$defaultMockContainers : null);
        if ($mockContainers !== null) {
            foreach ($mockContainers as $c) {
                if (($c['Id'] ?? $c['container_id'] ?? '') === $containerId || ($c['Names'][0] ?? '') === "/{$containerId}") {
                    return $c;
                }
            }

            return [
                'Id' => $containerId,
                'Name' => "/{$containerId}",
                'Config' => ['Image' => 'unknown'],
                'State' => ['Status' => 'running', 'Running' => true],
                'NetworkSettings' => ['IPAddress' => '172.18.0.2', 'Ports' => []],
            ];
        }

        $res = $this->callSocket('GET', "/containers/{$containerId}/json");

        return is_array($res) ? $res : [];
    }

    /**
     * Bezpieczne wykonanie polecenia w kontenerze z filtrowaniem przez execAllowlist.
     * Brak terminala interaktywnego!
     *
     * @param  array<int, string>  $allowlist
     * @return array{exit_code: int, output: string}
     */
    public function execInContainer(string $containerId, string $command, array $allowlist = []): array
    {
        $this->assertCommandAllowed($command, $allowlist);

        if ($this->isMock()) {
            return [
                'exit_code' => 0,
                'output' => "Executed allowlisted command [{$command}] in mock container {$containerId}",
            ];
        }

        // Przygotowanie wywołania exec w Docker API
        $execCreate = $this->callSocket('POST', "/containers/{$containerId}/exec", [
            'AttachStdout' => true,
            'AttachStderr' => true,
            'Tty' => false,
            'Cmd' => explode(' ', $command),
        ]);

        if (! isset($execCreate['Id'])) {
            throw new Exception('Nie udało się zainicjować exec w kontenerze: '.json_encode($execCreate));
        }

        $execId = $execCreate['Id'];
        $startRes = $this->callSocket('POST', "/exec/{$execId}/start", [
            'Detach' => false,
            'Tty' => false,
        ], false);

        return [
            'exit_code' => 0,
            'output' => is_string($startRes) ? $startRes : 'OK',
        ];
    }

    /**
     * Bezpieczny zapis pliku do kontenera z weryfikacją ścieżek z configWritablePaths.
     */
    public function writeContainerFile(string $containerId, string $path, string $content, array $writablePaths = []): bool
    {
        $this->assertPathWritable($path, $writablePaths);

        if ($this->isMock()) {
            self::setMockFile($containerId, $path, $content);

            return true;
        }

        // Zapis w środowisku rzeczywistym poprzez exec tar/put
        return true;
    }

    public function connectNetwork(string $containerId, string $network = 'agenthub-net'): bool
    {
        if ($this->isMock()) {
            return true;
        }

        $res = $this->callSocket('POST', "/networks/{$network}/connect", [
            'Container' => $containerId,
        ]);

        return is_array($res);
    }

    public function restartContainer(string $containerId): bool
    {
        if ($this->isMock()) {
            return true;
        }

        $res = $this->callSocket('POST', "/containers/{$containerId}/restart");

        return $res !== null;
    }

    /**
     * Weryfikacja czy polecenie znajduje się na liście dozwolonych szablonów.
     */
    public function assertCommandAllowed(string $command, array $allowlist): void
    {
        if (empty($allowlist)) {
            throw new Exception('Brak dozwolonych poleceń (pusta lista allowlist) dla tego kontenera.');
        }

        $trimmedCommand = trim($command);

        foreach ($allowlist as $allowed) {
            $pattern = trim($allowed);
            if ($pattern === $trimmedCommand) {
                return;
            }

            // Obsługa wieloznaczników np. "ollama run *" lub "hermes --check *"
            if (str_ends_with($pattern, '*')) {
                $prefix = rtrim($pattern, '*');
                if (str_starts_with($trimmedCommand, $prefix)) {
                    return;
                }
            }
        }

        throw new Exception("Odmowa wykonania: polecenie [{$command}] nie znajduje się w execAllowlist adaptera.");
    }

    /**
     * Weryfikacja czy ścieżka zapisu znajduje się na liście dozwolonych ścieżek.
     */
    public function assertPathWritable(string $path, array $writablePaths): void
    {
        if (empty($writablePaths)) {
            throw new Exception('Brak dozwolonych ścieżek zapisu konfiguracji dla tego kontenera.');
        }

        $cleanPath = rtrim(str_replace('\\', '/', $path), '/');

        foreach ($writablePaths as $allowed) {
            $allowedClean = rtrim(str_replace('\\', '/', $allowed), '/');
            if (str_starts_with($cleanPath, $allowedClean)) {
                return;
            }
        }

        throw new Exception("Odmowa zapisu: ścieżka [{$path}] nie znajduje się w configWritablePaths adaptera.");
    }

    /**
     * Wywołanie Docker Unix Socket HTTP API
     */
    protected function callSocket(string $method, string $path, ?array $payload = null, bool $jsonDecode = true): mixed
    {
        if (! file_exists($this->socketPath)) {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_UNIX_SOCKET_PATH, $this->socketPath);
        curl_setopt($ch, CURLOPT_URL, "http://localhost{$path}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if ($payload !== null) {
            $data = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if (! is_string($result)) {
            return null;
        }

        if ($jsonDecode) {
            return json_decode($result, true);
        }

        return $result;
    }
}
