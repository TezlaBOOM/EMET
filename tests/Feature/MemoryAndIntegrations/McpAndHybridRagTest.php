<?php

declare(strict_types=1);

namespace Tests\Feature\MemoryAndIntegrations;

use App\Contracts\Integrations\McpHubInterface;
use App\Models\Agent;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\McpServer;
use App\Models\MemoryCollection;
use App\Models\MemoryRelation;
use App\Models\Skill;
use App\Models\User;
use App\Services\MemoryService\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class McpAndHybridRagTest extends TestCase
{
    use RefreshDatabase;

    protected LlmProvider $provider;

    protected LlmAccount $account;

    protected MemoryCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = LlmProvider::create([
            'name' => 'OpenAI Embeddings',
            'slug' => 'openai',
            'driver' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'text-embedding-3-small',
            'is_active' => true,
        ]);

        $this->account = LlmAccount::create([
            'provider_id' => $this->provider->id,
            'name' => 'Mock Embedding Key',
            'api_key' => 'mock-embed-key',
            'weight' => 10,
            'current_status' => 'active',
        ]);

        $this->collection = MemoryCollection::create([
            'name' => 'Dokumentacja Architektury',
            'slug' => 'arch-docs',
            'vector_store' => 'pgvector',
            'embedding_provider_id' => $this->provider->id,
            'embedding_model' => 'text-embedding-3-small',
            'dimensions' => 128,
            'distance_metric' => 'cosine',
            'is_default' => true,
        ]);
    }

    public function test_mcp_client_discovers_tools_and_syncs_them_to_skills_registry(): void
    {
        Http::fake([
            'https://mcp.github.example.com/rpc' => Http::response([
                'jsonrpc' => '2.0',
                'id' => '1',
                'result' => [
                    'tools' => [
                        [
                            'name' => 'create_pull_request',
                            'description' => 'Tworzy nowy Pull Request w repozytorium GitHub',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'repo' => ['type' => 'string'],
                                    'title' => ['type' => 'string'],
                                ],
                                'required' => ['repo', 'title'],
                            ],
                        ],
                        [
                            'name' => 'list_issues',
                            'description' => 'Pobiera listę otwartych zgłoszeń z GitHuba',
                            'inputSchema' => [
                                'type' => 'object',
                                'properties' => [
                                    'repo' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $server = McpServer::create([
            'name' => 'GitHub Enterprise MCP',
            'slug' => 'github-mcp',
            'transport' => 'streamable_http',
            'endpoint_url' => 'https://mcp.github.example.com/rpc',
            'auth_token' => 'ghp_secret_mcp_token_123456',
            'status' => 'active',
        ]);

        /** @var McpHubInterface $mcpHub */
        $mcpHub = app(McpHubInterface::class);
        $syncResult = $mcpHub->syncToolsToSkills($server);

        $this->assertEquals(2, $syncResult['created']);
        $this->assertEquals(2, $syncResult['total']);

        $server->refresh();
        $this->assertEquals(2, $server->synced_skills_count);
        $this->assertCount(2, $server->discovered_tools);

        // Weryfikacja utworzenia rekordów w tabeli skills (typ = mcp)
        $prSkill = Skill::where('slug', 'mcp-github-mcp-create-pull-request')->first();
        $this->assertNotNull($prSkill);
        $this->assertEquals('mcp', $prSkill->type);
        $this->assertEquals('active', $prSkill->status);
        $this->assertNotNull($prSkill->current_version_id);
    }

    public function test_builtin_mcp_server_endpoint_handles_json_rpc_requests(): void
    {
        Agent::create([
            'name' => 'Architekt Systemowy',
            'slug' => 'architekt-systemowy',
            'system_prompt' => 'Projektujesz systemy rozproszone.',
            'primary_model' => 'gpt-4o',
            'is_active' => true,
            'internet_mode' => 'allowlist',
            'context_mode' => 'stateful',
        ]);

        // 1. Test initialize
        $initResponse = $this->postJson(route('api.v1.mcp.rpc'), [
            'jsonrpc' => '2.0',
            'id' => 101,
            'method' => 'initialize',
        ]);

        $initResponse->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'Projekt-Emet MCP Hub')
            ->assertJsonPath('id', 101);

        // 2. Test tools/list
        $toolsResponse = $this->postJson(route('api.v1.mcp.rpc'), [
            'jsonrpc' => '2.0',
            'id' => 102,
            'method' => 'tools/list',
        ]);

        $toolsResponse->assertOk();
        $toolNames = collect($toolsResponse->json('result.tools'))->pluck('name')->all();
        $this->assertContains('agenthub_list_agents', $toolNames);
        $this->assertContains('agenthub_memory_hybrid_search', $toolNames);

        // 3. Test tools/call -> agenthub_list_agents
        $callResponse = $this->postJson(route('api.v1.mcp.rpc'), [
            'jsonrpc' => '2.0',
            'id' => 103,
            'method' => 'tools/call',
            'params' => [
                'name' => 'agenthub_list_agents',
                'arguments' => [],
            ],
        ]);

        $callResponse->assertOk();
        $textContent = $callResponse->json('result.content.0.text');
        $this->assertStringContainsString('architekt-systemowy', (string) $textContent);
    }

    public function test_hybrid_rag_extracts_entities_and_combines_dense_bm25_and_graph_expansion(): void
    {
        /** @var EmbeddingService $embeddingService */
        $embeddingService = app(EmbeddingService::class);

        // Indeksujemy 2 dokumenty zawierające techniczne identyfikatory i powiązane encje
        $embeddingService->ingestDocument(
            collection: $this->collection,
            title: 'Brama Modeli i Qdrant',
            content: 'Usługa `LlmGateway` zależy od magazynu **QdrantVectorStore** oraz obsługuje kod błędu `ERR-429` przy przekroczeniu limitów.',
        );

        $embeddingService->ingestDocument(
            collection: $this->collection,
            title: 'Konfiguracja Sandboxa Skilli',
            content: 'Serwis `SkillSandboxService` weryfikuje sumy kontrolne SHA-256 i zwraca kod `ERR-409` przy próbie usunięcia aktywnego skilla.',
        );

        // 1. Weryfikujemy, że encje i relacje zostały automatycznie zapisane w Grafie Wiedzy (GraphRAG)
        $this->assertDatabaseHas('memory_entities', [
            'collection_id' => $this->collection->id,
            'normalized_name' => 'llmgateway',
        ]);
        $this->assertDatabaseHas('memory_entities', [
            'collection_id' => $this->collection->id,
            'normalized_name' => 'err-409',
        ]);
        $this->assertGreaterThan(0, MemoryRelation::where('collection_id', $this->collection->id)->count());

        // 2. Wyszukiwanie hybrydowe po dokładnym kodzie błędu ERR-409 powinno wypromować drugi dokument na 1. miejsce dzięki BM25 + GraphRAG + Reranker
        $results = $embeddingService->search($this->collection, 'ERR-409 SkillSandboxService', 5, 'hybrid');

        $this->assertNotEmpty($results);
        $topResult = $results[0];
        $this->assertEquals('Konfiguracja Sandboxa Skilli', $topResult['chunk']?->title);
        $this->assertGreaterThan(0.5, $topResult['bm25_score']);
        $this->assertGreaterThan(0.0, $topResult['graph_score']);
        $this->assertContains('ERR-409', $topResult['matched_entities']);

        // 3. Weryfikacja danych dla kanwy Obsidian (zawiera węzły entity)
        $graphData = $embeddingService->getGraphData($this->collection);
        $entityNodes = collect($graphData['nodes'])->where('group', 'entity');
        $this->assertNotEmpty($entityNodes);
    }

    public function test_integrations_mcp_hub_ui_renders_and_stores_server(): void
    {
        Permission::firstOrCreate(['name' => 'integrations.manage', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo('integrations.manage');

        $viewResponse = $this->actingAs($admin)->get(route('integrations.mcp.index'));
        $viewResponse->assertOk();
        $viewResponse->assertSee(__('integrations.mcp_title'));

        $storeResponse = $this->actingAs($admin)->post(route('integrations.mcp.store'), [
            'name' => 'Local Postgres MCP',
            'transport' => 'stdio',
            'command' => 'npx -y @modelcontextprotocol/server-postgres',
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('mcp_servers', [
            'name' => 'Local Postgres MCP',
            'transport' => 'stdio',
        ]);
    }
}
