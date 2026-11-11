<?php

declare(strict_types=1);

namespace Tests\Feature\V2AutonomousAndRagParser;

use App\Contracts\Llm\CompletionRequest;
use App\Contracts\Llm\LlmGatewayInterface;
use App\Contracts\Llm\SelfReflectionCriticInterface;
use App\Contracts\Memory\DocumentParserInterface;
use App\Contracts\Telemetry\DistributedTracerInterface;
use App\Models\Agent;
use App\Models\AgentCooperation;
use App\Models\AgentSkill;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\LlmAccount;
use App\Models\LlmProvider;
use App\Models\MemoryCollection;
use App\Models\Skill;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentContextBuilder;
use App\Services\MemoryService\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class V2AutonomousCriticParserAndOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_reflection_critic_repairs_broken_json_and_populates_missing_required_keys(): void
    {
        /** @var SelfReflectionCriticInterface $critic */
        $critic = app(SelfReflectionCriticInterface::class);

        $brokenJsonOutput = "```json\n{\"status\": \"ok\", \"summary\": \"Raport gotowy\"\n```";

        $inspection = $critic->inspectResponse($brokenJsonOutput, [
            'expect_json' => true,
            'required_keys' => ['status', 'summary', 'confidence'],
        ]);

        $this->assertNotNull($inspection['repaired_content']);
        $decoded = json_decode((string) $inspection['repaired_content'], true);
        $this->assertIsArray($decoded);
        $this->assertSame('ok', $decoded['status']);
        $this->assertArrayHasKey('confidence', $decoded);
    }

    public function test_document_parser_converts_csv_and_html_tables_into_structured_markdown_for_rag_ingestion(): void
    {
        /** @var DocumentParserInterface $parser */
        $parser = app(DocumentParserInterface::class);

        $csv = "Service,Port,Status\nqdrant,6333,healthy\nollama,11434,healthy";
        $parsedCsv = $parser->parseToStructuredMarkdown($csv, 'csv', 'Usługi Lokalne');

        $this->assertSame('csv', $parsedCsv['format']);
        $this->assertSame(1, $parsedCsv['tables_extracted']);
        $this->assertSame(2, $parsedCsv['rows_count']);
        $this->assertStringContainsString('| Service | Port | Status |', $parsedCsv['normalized_markdown']);
        $this->assertStringContainsString('| qdrant | 6333 | healthy |', $parsedCsv['normalized_markdown']);

        $html = '<html><body><h1>Macierz Modeli</h1><table><tr><th>Model</th><th>Kontekst</th></tr><tr><td>Gemini 2.5 Pro</td><td>1000000</td></tr></table></body></html>';
        $parsedHtml = $parser->parseToStructuredMarkdown($html, 'html');

        $this->assertSame('html', $parsedHtml['format']);
        $this->assertSame(1, $parsedHtml['tables_extracted']);
        $this->assertStringContainsString('| Model | Kontekst |', $parsedHtml['normalized_markdown']);
        $this->assertStringContainsString('| Gemini 2.5 Pro | 1000000 |', $parsedHtml['normalized_markdown']);

        $provider = LlmProvider::create([
            'name' => 'Ollama',
            'slug' => 'ollama',
            'driver' => 'ollama',
            'base_url' => 'http://localhost:11434',
            'default_model' => 'nomic-embed-text',
            'is_enabled' => true,
        ]);

        LlmAccount::create([
            'provider_id' => $provider->id,
            'name' => 'Local Ollama',
            'api_key' => 'local',
            'current_status' => 'active',
        ]);

        Http::fake([
            '*' => Http::response(['embedding' => array_fill(0, 16, 0.1)], 200),
        ]);

        $collection = MemoryCollection::create([
            'name' => 'Tabela Infrastruktury',
            'slug' => 'tabela-infrastruktury',
            'vector_store' => 'qdrant',
            'embedding_provider_id' => $provider->id,
            'embedding_model' => 'nomic-embed-text',
            'dimensions' => 16,
        ]);

        /** @var EmbeddingService $embeddingService */
        $embeddingService = app(EmbeddingService::class);
        $chunks = $embeddingService->ingestDocument($collection, 'Usługi CSV', $csv, ['format' => 'csv']);

        $this->assertNotEmpty($chunks);
        $this->assertSame('csv', $chunks[0]->metadata['detected_format'] ?? null);
        $this->assertSame(1, $chunks[0]->metadata['tables_extracted'] ?? 0);
    }

    public function test_llm_gateway_orchestrates_workspace_finops_downgrade_and_opentelemetry_spans(): void
    {
        $ollamaProvider = LlmProvider::create([
            'name' => 'Ollama Local',
            'slug' => 'ollama',
            'driver' => 'ollama',
            'base_url' => 'http://localhost:11434',
            'default_model' => 'llama3.2',
            'is_enabled' => true,
        ]);

        LlmAccount::create([
            'provider_id' => $ollamaProvider->id,
            'name' => 'Local Ollama Account',
            'api_key' => 'ollama-local',
            'current_status' => 'active',
        ]);

        Http::fake([
            '*/api/chat*' => Http::response([
                'model' => 'llama3.2',
                'message' => ['role' => 'assistant', 'content' => '{"status":"ok","mode":"local_fallback"'],
                'prompt_eval_count' => 25,
                'eval_count' => 15,
            ], 200),
        ]);

        $workspace = Workspace::create([
            'name' => 'Zespół Analityczny',
            'slug' => 'zespol-analityczny',
            'daily_budget_usd' => 5.00,
            'monthly_budget_usd' => 100.00,
            'daily_token_limit' => 100000,
            'spent_today_usd' => 6.50,
            'fallback_to_local_on_exceed' => true,
            'fallback_model' => 'llama3.2',
            'is_active' => true,
        ]);

        /** @var DistributedTracerInterface $tracer */
        $tracer = app(DistributedTracerInterface::class);
        $trace = $tracer->startTrace('api.request', 'agenthub-core');

        /** @var LlmGatewayInterface $gateway */
        $gateway = app(LlmGatewayInterface::class);

        $response = $gateway->complete(new CompletionRequest(
            messages: [['role' => 'user', 'content' => 'Zwróć status JSON']],
            model: 'gpt-4o',
            metadata: [
                'workspace_id' => $workspace->id,
                'trace_id' => $trace['trace_id'],
                'parent_span_id' => $trace['root_span_id'],
                'skip_cache' => true,
                'self_reflection' => [
                    'expect_json' => true,
                    'required_keys' => ['status', 'mode'],
                ],
            ]
        ));

        $decoded = json_decode($response->content, true);
        $this->assertIsArray($decoded);
        $this->assertSame('local_fallback', $decoded['mode'] ?? null);
        $this->assertGreaterThan(0, $workspace->fresh()->tokens_used_today);

        $waterfall = $tracer->getWaterfallTrace($trace['trace_id']);
        $this->assertGreaterThanOrEqual(2, $waterfall['span_count']);
    }

    public function test_user_can_attach_catalog_and_custom_skills_to_agent_and_detach_them_from_agents_tab(): void
    {
        Permission::findOrCreate('agents.view');
        Permission::findOrCreate('agents.manage');

        $user = User::factory()->create();
        $user->givePermissionTo(['agents.view', 'agents.manage']);

        $agent = Agent::create([
            'name' => 'Agent Operacyjny',
            'slug' => 'agent-operacyjny',
            'runtime' => 'hermes',
            'model' => 'llama3.2',
            'status' => 'active',
        ]);

        $catalogSkill = Skill::create([
            'name' => 'Wyszukiwarka Semantyczna RAG',
            'slug' => 'rag-semantic-search',
            'description' => 'Skill wyszukiwania w bazie Qdrant',
            'type' => 'tool',
            'status' => Skill::STATUS_ACTIVE,
            'current_version' => '1.0.0',
        ]);

        $attachCatalogResponse = $this->actingAs($user)->post(route('agents.skills.attach', $agent), [
            'skill_id' => $catalogSkill->id,
        ]);

        $attachCatalogResponse->assertRedirect();
        $this->assertDatabaseHas('agent_skills', [
            'agent_id' => $agent->id,
            'skill_id' => $catalogSkill->id,
            'name' => 'Wyszukiwarka Semantyczna RAG',
            'driver' => 'rag-semantic-search',
            'is_enabled' => true,
        ]);

        $attachCustomResponse = $this->actingAs($user)->post(route('agents.skills.attach', $agent), [
            'custom_name' => 'Konektor ERP',
            'custom_driver' => 'erp_http_tool',
        ]);

        $attachCustomResponse->assertRedirect();
        $this->assertDatabaseHas('agent_skills', [
            'agent_id' => $agent->id,
            'name' => 'Konektor ERP',
            'driver' => 'erp_http_tool',
            'is_enabled' => true,
        ]);

        $attachedSkill = AgentSkill::where('agent_id', $agent->id)
            ->where('driver', 'erp_http_tool')
            ->firstOrFail();

        $detachResponse = $this->actingAs($user)->delete(route('agents.skills.detach', [$agent, $attachedSkill]));
        $detachResponse->assertRedirect();

        $this->assertDatabaseMissing('agent_skills', [
            'id' => $attachedSkill->id,
        ]);
    }

    public function test_multi_agent_cooperation_executes_discussion_and_overheat_guard_trips_and_cools_down(): void
    {
        Permission::findOrCreate('agents.view');
        Permission::findOrCreate('agents.manage');

        $user = User::factory()->create();
        $user->givePermissionTo(['agents.view', 'agents.manage']);

        $agentA = Agent::create([
            'name' => 'Architekt Systemowy',
            'slug' => 'architekt-systemowy',
            'runtime' => 'hermes',
            'model' => 'llama3.2',
            'status' => 'active',
        ]);

        $agentB = Agent::create([
            'name' => 'Audytor Bezpieczeństwa',
            'slug' => 'audytor-bezpieczenstwa',
            'runtime' => 'hermes',
            'model' => 'llama3.2',
            'status' => 'active',
        ]);

        $storeResponse = $this->actingAs($user)->post(route('agents.cooperation.store'), [
            'name' => 'Zespół Architektury i Bezpieczeństwa',
            'description' => 'Wspólna debata nad architekturą mikroserwisów',
            'topology' => 'round_table',
            'coordinator_agent_id' => $agentA->id,
            'participant_agent_ids' => [$agentA->id, $agentB->id],
            'overheat_guard_enabled' => true,
            'max_rounds' => 2,
            'max_tokens_budget' => 5000,
            'max_temperature_score' => 85,
            'repetition_threshold' => 0.80,
            'auto_synthesize_on_overheat' => true,
        ]);

        $storeResponse->assertRedirect();
        $cooperation = AgentCooperation::firstOrFail();

        // 1. Normalne uruchomienie dyskusji i zadania w granicach limitu rund (2 rundy)
        $runNormal = $this->actingAs($user)->post(route('agents.cooperation.run', $cooperation), [
            'task_prompt' => 'Zaprojektuj bramę autoryzacji API z rotacją kluczy.',
            'rounds_override' => 1,
        ]);

        $runNormal->assertRedirect();
        $cooperation->refresh();

        $this->assertSame('completed', $cooperation->status);
        $this->assertCount(2, $cooperation->discussion_log ?? []);
        $this->assertNotEmpty($cooperation->final_synthesis);
        $this->assertGreaterThan(22.0, $cooperation->current_temperature);

        // 2. Wymuszenie przekroczenia liczby rund (rounds_override = 5 przy max_rounds = 2) -> zadziałanie Strażnika Przegrzania
        $runOverheat = $this->actingAs($user)->post(route('agents.cooperation.run', $cooperation), [
            'task_prompt' => 'Kontynuuj głęboką analizę wszystkich wektorów ataku w pętli.',
            'rounds_override' => 5,
        ]);

        $runOverheat->assertRedirect();
        $cooperation->refresh();

        $this->assertSame('overheated', $cooperation->status);
        $this->assertSame('overheated', $cooperation->thermal_status);
        $this->assertNotEmpty($cooperation->overheat_events);
        $this->assertStringContainsString('SYNTEZA AWARYJNA', (string) $cooperation->final_synthesis);
        $this->assertNotEmpty($cooperation->preserved_context);
        $this->assertGreaterThanOrEqual(1, $cooperation->context_reloads_count);

        // 3. Przeładowanie czatu z zachowaniem kontekstu (aby agenci nie zmyślali po przeciągnięciu rozmowy)
        $cooldownResponse = $this->actingAs($user)->post(route('agents.cooperation.cooldown', $cooperation), [
            'custom_context_note' => 'Zachowaj architekturę OAuth2 + mTLS.',
        ]);
        $cooldownResponse->assertRedirect();
        $cooperation->refresh();

        $this->assertSame(22.0, $cooperation->current_temperature);
        $this->assertSame('nominal', $cooperation->thermal_status);
        $this->assertSame('cooled_down', $cooperation->status);
        $this->assertStringContainsString('OAuth2 + mTLS', (string) $cooperation->preserved_context);
        $this->assertCount(1, $cooperation->discussion_log ?? []);

        // 4. Przeładowanie standardowego czatu 1-na-1 z zachowaniem kontekstu (ChatController::reloadWithContext)
        Permission::findOrCreate('agents.chat');
        $user->givePermissionTo('agents.chat');

        $conversation = ChatConversation::create([
            'id' => '00000000-0000-0000-0000-000000000123',
            'user_id' => $user->id,
            'agent_id' => $agentA->id,
            'title' => 'Długa rozmowa architektoniczna',
        ]);

        ChatMessage::create([
            'id' => '00000000-0000-0000-0000-000000000124',
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Używamy bazy PostgreSQL 17 oraz Qdrant na porcie 6333.',
            'created_at' => now()->subMinutes(5),
        ]);

        ChatMessage::create([
            'id' => '00000000-0000-0000-0000-000000000125',
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Przyjąłem: PostgreSQL 17 + Qdrant 6333 skonfigurowane jako główny klaster pamięci.',
            'created_at' => now()->subMinutes(4),
        ]);

        $reloadChatResp = $this->actingAs($user)->post(route('chat.reload-context', $conversation));
        $reloadChatResp->assertRedirect();

        $conversation->refresh();
        $this->assertSame(1, $conversation->settings['context_reloads_count'] ?? 0);
        $this->assertStringContainsString('PostgreSQL 17', $conversation->settings['preserved_context_summary'] ?? '');
        $this->assertCount(1, $conversation->messages);

        /** @var AgentContextBuilder $builder */
        $builder = app(AgentContextBuilder::class);
        $builtMessages = $builder->build($agentA, 'Jakiej bazy używamy?', $conversation);
        $this->assertStringContainsString('PostgreSQL 17', $builtMessages[0]['content'] ?? '');
    }
}
