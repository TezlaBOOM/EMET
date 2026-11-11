<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

use App\Models\McpServer;

interface McpHubInterface
{
    /**
     * Odpytuje zewnętrzny serwer MCP metodą JSON-RPC 2.0 `tools/list` i aktualizuje pole `discovered_tools`.
     *
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function discoverTools(McpServer $server): array;

    /**
     * Synchronizuje odkryte narzędzia z serwera MCP do rejestru skilli (`skills` + `skill_versions`).
     *
     * @return array{created: int, updated: int, total: int}
     */
    public function syncToolsToSkills(McpServer $server): array;

    /**
     * Wywołuje narzędzie na zewnętrznym serwerze MCP metodą JSON-RPC 2.0 `tools/call`.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{success: bool, content: mixed, error?: string}
     */
    public function callExternalTool(McpServer $server, string $toolName, array $arguments = []): array;

    /**
     * Obsługuje przychodzące żądanie JSON-RPC 2.0 dla wbudowanego serwera MCP Projekt-Emet
     * (`initialize`, `tools/list`, `tools/call`, `resources/list`, `ping`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleServerRpc(array $payload): array;

    /**
     * Zwraca manifest narzędzi i zasobów wystawianych przez wbudowany serwer MCP Projekt-Emet.
     *
     * @return array{
     *     protocolVersion: string,
     *     serverInfo: array{name: string, version: string},
     *     tools: list<array{name: string, description: string, inputSchema: array<string, mixed>}>,
     *     resources: list<array{uri: string, name: string, mimeType: string}>
     * }
     */
    public function getServerManifest(): array;
}

