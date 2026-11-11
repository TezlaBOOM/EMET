<?php

declare(strict_types=1);

namespace Tests\Feature\EnterpriseAndMultimodal;

use App\Contracts\Chat\MultimodalArtifactInterface;
use App\Contracts\FinOps\WorkspaceFinOpsInterface;
use App\Contracts\Integrations\ChatOpsChannelInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Skills\FederatedSkillHubInterface;
use App\Models\ExternalChannel;
use App\Models\FederatedRegistry;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioVersion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinOpsChatOpsMultimodalAndHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_finops_quotas_trigger_alerts_and_fallback_to_local_model_on_exceed(): void
    {
        $workspace = Workspace::create([
            'name' => 'Zespół Data Science',
            'slug' => 'zespol-data-science',
            'daily_budget_usd' => 10.0,
            'monthly_budget_usd' => 100.0,
            'daily_token_limit' => 100000,
            'alert_threshold_pct' => 80,
            'fallback_to_local_on_exceed' => true,
            'fallback_model' => 'ollama/llama3.2',
            'is_active' => true,
        ]);

        /** @var WorkspaceFinOpsInterface $finOps */
        $finOps = app(WorkspaceFinOpsInterface::class);

        // Przed przekroczeniem budżetu model pozostaje bez zmian
        $beforeRouting = $finOps->resolveEffectiveModel($workspace, 'gpt-4o');
        $this->assertFalse($beforeRouting['downgraded_to_local']);
        $this->assertSame('gpt-4o', $beforeRouting['effective_model']);

        // Zużycie 8.50 USD (85% budżetu dziennego) -> wyzwala alert progowy 80%
        $usage1 = $finOps->recordUsage($workspace, 40000, 8.50);
        $this->assertTrue($usage1['alert_triggered']);
        $this->assertFalse($usage1['budget_exceeded']);

        // Kolejne 2.50 USD (łącznie 11.00 USD > 10.00 USD) -> przekroczenie budżetu i fallback na model lokalny
        $usage2 = $finOps->recordUsage($workspace->fresh(), 15000, 2.50);
        $this->assertTrue($usage2['budget_exceeded']);

        $afterRouting = $finOps->resolveEffectiveModel($workspace->fresh(), 'gpt-4o');
        $this->assertTrue($afterRouting['downgraded_to_local']);
        $this->assertSame('ollama/llama3.2', $afterRouting['effective_model']);
        $this->assertNotEmpty($afterRouting['reason']);
    }

    public function test_chatops_channel_webhook_resumes_human_in_the_loop_scenario(): void
    {
        $user = User::factory()->create();

        $scenario = Scenario::create([
            'name' => 'Wdrożenie Produkcyjne z Akceptacją',
            'slug' => 'wdrozenie-produkcyjne-z-akceptacja',
            'status' => Scenario::STATUS_PUBLISHED,
            'created_by' => $user->id,
        ]);

        $graph = [
            'nodes' => [
                'n1' => [
                    'id' => 'n1',
                    'type' => 'start',
                    'name' => 'start',
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]],
                    ],
                ],
                'n2' => [
                    'id' => 'n2',
                    'type' => 'human',
                    'name' => 'human_approval',
                    'data' => ['prompt' => 'Czy zatwierdzasz wdrożenie na produkcję?'],
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n3', 'input' => 'input_1']]],
                    ],
                ],
                'n3' => [
                    'id' => 'n3',
                    'type' => 'end',
                    'name' => 'end',
                ],
            ],
        ];

        $version = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => 1,
            'graph' => $graph,
            'draft' => false,
        ]);
        $scenario->update(['current_version_id' => $version->id]);

        /** @var ScenarioEngineInterface $engine */
        $engine = app(ScenarioEngineInterface::class);
        $runId = $engine->startRun($scenario->id, $version->id, ['env' => 'production']);

        $pausedRun = ScenarioRun::findOrFail($runId);
        $this->assertSame(ScenarioRun::STATUS_PAUSED, $pausedRun->status);

        $channel = ExternalChannel::create([
            'name' => 'Slack DevOps Approvals',
            'slug' => 'slack-devops-approvals',
            'platform' => 'slack',
            'scenario_id' => $scenario->id,
            'is_active' => true,
        ]);

        /** @var ChatOpsChannelInterface $chatOps */
        $chatOps = app(ChatOpsChannelInterface::class);
        $approvalCard = $chatOps->buildHumanApprovalPayload($channel, $runId, 'n2', 'Zatwierdź wdrożenie #'.$runId);
        $this->assertCount(2, $approvalCard['interactive_buttons']);

        // Wywołanie publicznego webhooka ChatOps z decyzją "approve"
        $response = $this->postJson(route('api.v1.hooks.channels', ['slug' => $channel->slug]), [
            'action' => 'approve',
            'run_id' => $runId,
            'node_id' => 'n2',
            'user' => 'admin_witek',
        ]);

        $response->assertOk();
        $response->assertJsonPath('event_type', 'human_in_the_loop_decision');
        $response->assertJsonPath('resumed_run_id', $runId);

        $resumedRun = ScenarioRun::findOrFail($runId);
        $this->assertSame(ScenarioRun::STATUS_SUCCESS, $resumedRun->status);
        $this->assertTrue($resumedRun->vars['human_input']['approved'] ?? false);
    }

    public function test_multimodal_artifact_service_extracts_live_canvas_artifacts_and_processes_vision_audio(): void
    {
        /** @var MultimodalArtifactInterface $multimodal */
        $multimodal = app(MultimodalArtifactInterface::class);

        $agentReply = <<<'TEXT'
Oto architektura przepływu oraz interaktywny komponent:
<artifact title="Panel KPI Sprzedaży" type="html"><div class="kpi">Przychód: +42%</div></artifact>

```mermaid
flowchart LR
    A[User] --> B[LlmGateway]
```
TEXT;

        $artifacts = $multimodal->extractAndStoreArtifacts('conv-uuid-1', 'msg-uuid-1', $agentReply);
        $this->assertCount(2, $artifacts);
        $this->assertSame('Panel KPI Sprzedaży', $artifacts[0]->title);
        $this->assertSame('html', $artifacts[0]->artifact_type);
        $this->assertSame('mermaid', $artifacts[1]->artifact_type);

        $visionInfo = $multimodal->processMultimodalAttachment([
            'mime_type' => 'image/png',
            'filename' => 'architecture.png',
            'content_base64' => base64_encode('fake-png-bytes'),
        ]);
        $this->assertSame('vision', $visionInfo['modality']);
        $this->assertStringStartsWith('data:image/png;base64,', $visionInfo['vision_data_url'] ?? '');

        $audioInfo = $multimodal->processMultimodalAttachment([
            'mime_type' => 'audio/wav',
            'filename' => 'voice_command.wav',
            'transcript_hint' => 'Uruchom raport dzienny dla zespołu R&D.',
        ]);
        $this->assertSame('audio_stt', $audioInfo['modality']);
        $this->assertStringContainsString('Uruchom raport dzienny', $audioInfo['processed_prompt_addition']);
    }

    public function test_federated_skill_hub_verifies_ed25519_signatures_and_rejects_tampered_packages(): void
    {
        /** @var FederatedSkillHubInterface $hub */
        $hub = app(FederatedSkillHubInterface::class);

        $keys = $hub->generateKeypair();
        $registry = FederatedRegistry::create([
            'name' => 'Official Projekt-Emet Hub',
            'slug' => 'official-projekt-emet-hub',
            'registry_url' => 'https://hub.agenthub.local/catalog.json',
            'public_key_ed25519' => $keys['public_key_hex'],
            'is_trusted' => true,
        ]);

        $definition = ['entrypoint' => 'run.php', 'runtime' => 'php8.5'];
        $manifest = [
            'name' => 'Analizator Kosztów FinOps',
            'slug' => 'analizator-kosztow-finops',
            'version' => '1.2.0',
            'type' => 'package',
            'author' => 'DeepMind Security Team',
            'definition' => $definition,
            'checksum_sha256' => hash('sha256', json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: ''),
        ];

        $signature = $hub->signManifest($manifest, $keys['secret_key_hex']);
        $this->assertTrue($hub->verifyManifestSignature($manifest, $signature, $keys['public_key_hex']));

        $installedSkill = $hub->installVerifiedPackage($registry, [
            'manifest' => $manifest,
            'signature_ed25519' => $signature,
        ]);

        $this->assertSame('analizator-kosztow-finops', $installedSkill->slug);
        $this->assertSame('1.2.0', $installedSkill->currentVersion?->version);

        // Próba instalacji paczki ze zmodyfikowanym manifestem musi rzucić wyjątek Ed25519
        $tamperedManifest = $manifest;
        $tamperedManifest['version'] = '9.9.9-backdoor';

        $this->expectException(RuntimeException::class);
        $hub->installVerifiedPackage($registry, [
            'manifest' => $tamperedManifest,
            'signature_ed25519' => $signature,
        ]);
    }

    public function test_v19_ui_views_are_accessible(): void
    {
        Permission::findOrCreate('system.manage', 'web');
        Permission::findOrCreate('integrations.manage', 'web');
        Permission::findOrCreate('agents.chat', 'web');
        Permission::findOrCreate('skills.view', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['system.manage', 'integrations.manage', 'agents.chat', 'skills.view']);

        $this->actingAs($user)->get(route('system.workspaces.index'))->assertOk();
        $this->actingAs($user)->get(route('integrations.channels.index'))->assertOk();
        $this->actingAs($user)->get(route('chat.artifacts'))->assertOk();
        $this->actingAs($user)->get(route('agents.hub.index'))->assertOk();
    }
}
