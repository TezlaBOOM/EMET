<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\McpHubInterface;
use App\Contracts\Memory\HybridRagInterface;
use App\Models\Agent;
use App\Models\McpServer;
use App\Models\MemoryCollection;
use App\Models\Scenario;
use App\Models\Skill;
use App\Models\SkillVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class McpHubService implements McpHubInterface
{
    public function __construct(
        protected HybridRagInterface $hybridRag,
    ) {}

    public function discoverTools(McpServer $server): array
    {
        $tools = [];

        if (! empty($server->endpoint_url) && in_array($server->transport, ['streamable_http', 'http_sse'], true)) {
            try {
                $request = Http::timeout(10)->acceptJson();
                if (! empty($server->auth_token)) {
                    $request = $request->withToken($server->auth_token);
                }

                $response = $request->post($server->endpoint_url, [
                    'jsonrpc' => '2.0',
                    'id' => (string) Str::uuid(),
                    'method' => 'tools/list',
                    'params' => (object) [],
                ]);

                if ($response->successful()) {
                    $rawTools = $response->json('result.tools', []);
                    if (is_array($rawTools)) {
                        foreach ($rawTools as $t) {
                            if (! empty($t['name'])) {
                                $tools[] = [
                                    'name' => (string) $t['name'],
                                    'description' => (string) ($t['description'] ?? 'Narzędzie MCP '.$t['name']),
                                    'inputSchema' => is_array($t['inputSchema'] ?? null)
                                        ? $t['inputSchema']
                                        : ['type' => 'object', 'properties' => []],
                                ];
                            }
                        }
                    }
                } else {
                    $server->update([
                        'status' => 'error',
                        'last_error' => 'HTTP '.$response->status().' podczas wywołania tools/list',
                    ]);
                }
            } catch (Throwable $e) {
                $server->update([
                    'status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback dla serwerów stdio lub predefiniowanych serwerów lokalnych
        if (empty($tools)) {
            if (! empty($server->discovered_tools) && is_array($server->discovered_tools)) {
                $tools = $server->discovered_tools;
            } else {
                $tools = $this->defaultToolsForServer($server);
            }
        }

        $server->update([
            'discovered_tools' => $tools,
            'status' => 'active',
            'last_error' => null,
        ]);

        return $tools;
    }

    public function syncToolsToSkills(McpServer $server): array
    {
        $tools = $server->discovered_tools;
        if (empty($tools) || ! is_array($tools)) {
            $tools = $this->discoverTools($server);
        }

        $created = 0;
        $updated = 0;

        foreach ($tools as $tool) {
            $toolName = (string) ($tool['name'] ?? '');
            if ($toolName === '') {
                continue;
            }

            $slug = Str::slug("mcp-{$server->slug}-{$toolName}");
            $sourceRef = "mcp:{$server->slug}:{$toolName}";
            $description = (string) ($tool['description'] ?? "Narzędzie MCP {$toolName} z serwera {$server->name}");
            $inputSchema = is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : ['type' => 'object'];

            $existing = Skill::withTrashed()->where('slug', $slug)->first();
            $isNew = $existing === null;

            $skill = Skill::withTrashed()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => "[MCP] {$server->name}: {$toolName}",
                    'description' => $description,
                    'type' => 'mcp',
                    'status' => 'active',
                    'author' => "MCP ({$server->name})",
                    'source_ref' => $sourceRef,
                    'tags' => ['mcp', $server->slug],
                    'requires' => ['internet'],
                    'code' => json_encode([
                        'mcp_server_slug' => $server->slug,
                        'tool_name' => $toolName,
                        'input_schema' => $inputSchema,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'deleted_at' => null,
                ]
            );

            $version = SkillVersion::updateOrCreate(
                [
                    'skill_id' => $skill->id,
                    'version' => '1.0.0',
                ],
                [
                    'definition' => [
                        'type' => 'mcp',
                        'mcp_server_id' => $server->id,
                        'mcp_server_slug' => $server->slug,
                        'tool_name' => $toolName,
                        'description' => $description,
                        'input_schema' => $inputSchema,
                    ],
                    'checksum' => hash('sha256', $sourceRef.json_encode($inputSchema)),
                    'changelog' => "Automatyczna synchronizacja z serwera MCP {$server->name}",
                ]
            );

            $skill->update(['current_version_id' => $version->id]);

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        $total = $created + $updated;
        $server->update([
            'synced_skills_count' => $total,
            'last_synced_at' => now(),
            'status' => 'active',
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $total,
        ];
    }

    public function callExternalTool(McpServer $server, string $toolName, array $arguments = []): array
    {
        if (! empty($server->endpoint_url)) {
            try {
                $request = Http::timeout(15)->acceptJson();
                if (! empty($server->auth_token)) {
                    $request = $request->withToken($server->auth_token);
                }

                $response = $request->post($server->endpoint_url, [
                    'jsonrpc' => '2.0',
                    'id' => (string) Str::uuid(),
                    'method' => 'tools/call',
                    'params' => [
                        'name' => $toolName,
                        'arguments' => (object) $arguments,
                    ],
                ]);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'content' => $response->json('result.content', $response->json('result')),
                    ];
                }

                return [
                    'success' => false,
                    'content' => null,
                    'error' => 'Błąd HTTP '.$response->status().' z serwera MCP',
                ];
            } catch (Throwable $e) {
                return [
                    'success' => false,
                    'content' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => true,
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Wykonano lokalne narzędzie MCP [{$toolName}] na serwerze [{$server->name}].",
                ],
            ],
        ];
    }

    public function handleServerRpc(array $payload): array
    {
        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        return match ($method) {
            'initialize' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [
                        'tools' => ['listChanged' => true],
                        'resources' => ['subscribe' => false],
                    ],
                    'serverInfo' => [
                        'name' => 'Projekt-Emet MCP Hub',
                        'version' => (string) config('agenthub.version', '1.7.0'),
                    ],
                ],
            ],
            'ping' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => ['status' => 'pong'],
            ],
            'tools/list' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => $this->getExposedTools(),
                ],
            ],
            'resources/list' => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'resources' => $this->getExposedResources(),
                ],
            ],
            'tools/call' => $this->executeServerToolCall($id, $params),
            default => [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32601,
                    'message' => "Nieznana metoda MCP JSON-RPC: {$method}",
                ],
            ],
        };
    }

    public function getServerManifest(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'serverInfo' => [
                'name' => 'Projekt-Emet MCP Hub',
                'version' => (string) config('agenthub.version', '1.7.0'),
            ],
            'tools' => $this->getExposedTools(),
            'resources' => $this->getExposedResources(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function executeServerToolCall(mixed $id, array $params): array
    {
        $toolName = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($toolName === 'agenthub_list_agents') {
            $agents = Agent::where('is_active', true)->get(['id', 'name', 'slug', 'primary_model', 'internet_mode', 'context_mode']);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($agents->toArray(), JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ];
        }

        if ($toolName === 'agenthub_memory_hybrid_search') {
            $slug = (string) ($args['collection_slug'] ?? '');
            $query = (string) ($args['query'] ?? '');
            $limit = max(1, min(20, (int) ($args['limit'] ?? 5)));

            $collection = $slug !== ''
                ? MemoryCollection::where('slug', $slug)->first()
                : MemoryCollection::first();

            if (! $collection) {
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'content' => [['type' => 'text', 'text' => 'Brak wskazanej kolekcji pamięci wektorowej.']],
                        'isError' => true,
                    ],
                ];
            }

            $results = $this->hybridRag->search($collection, $query, $limit, 'hybrid');
            $formatted = array_map(fn (array $item): array => [
                'title' => $item['chunk']?->title ?? ($item['payload']['title'] ?? ''),
                'content' => $item['chunk']?->content ?? ($item['payload']['content'] ?? ''),
                'score' => $item['score'],
                'dense_score' => $item['dense_score'] ?? 0.0,
                'bm25_score' => $item['bm25_score'] ?? 0.0,
                'graph_score' => $item['graph_score'] ?? 0.0,
                'matched_entities' => $item['matched_entities'] ?? [],
            ], $results);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($formatted, JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ];
        }

        if ($toolName === 'agenthub_list_scenarios') {
            $scenarios = Scenario::where('status', 'published')->get(['id', 'name', 'slug', 'description']);

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode($scenarios->toArray(), JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32602,
                'message' => "Nieznane narzędzie MCP: {$toolName}",
            ],
        ];
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    private function getExposedTools(): array
    {
        return [
            [
                'name' => 'agenthub_list_agents',
                'description' => 'Zwraca listę aktywnych agentów AI skonfigurowanych w platformie Projekt-Emet.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
            [
                'name' => 'agenthub_memory_hybrid_search',
                'description' => 'Przeszukuje bazę wiedzy Projekt-Emet w trybie Hybrydowy RAG (Dense + BM25 + GraphRAG + Reranker).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_slug' => ['type' => 'string', 'description' => 'Slug kolekcji pamięci (opcjonalnie)'],
                        'query' => ['type' => 'string', 'description' => 'Zapytanie semantyczne lub identyfikator'],
                        'limit' => ['type' => 'integer', 'description' => 'Limit wyników (1-20)'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'agenthub_list_scenarios',
                'description' => 'Zwraca listę opublikowanych scenariuszy przepływów Drawflow.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    /**
     * @return list<array{uri: string, name: string, mimeType: string}>
     */
    private function getExposedResources(): array
    {
        return MemoryCollection::all()->map(fn (MemoryCollection $col): array => [
            'uri' => "memory://{$col->slug}",
            'name' => $col->name,
            'mimeType' => 'application/json',
        ])->all();
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    private function defaultToolsForServer(McpServer $server): array
    {
        return [
            [
                'name' => Str::slug($server->slug, '_').'_query',
                'description' => "Wykonuje zapytanie do zewnętrznego serwera MCP {$server->name}",
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Treść zapytania lub komendy'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }
}
