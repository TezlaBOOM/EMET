<?php

declare(strict_types=1);

namespace Tests\Feature\ObservabilityAndEvals;

use App\Contracts\Agents\AgentEvaluationInterface;
use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Telemetry\DistributedTracerInterface;
use App\Models\Agent;
use App\Models\AgentEvalSuite;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EvalsTimeTravelAndOtelTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_evaluation_service_runs_golden_dataset_and_ab_benchmark(): void
    {
        $agent = Agent::create([
            'name' => 'Architekt Systemowy',
            'slug' => 'architekt-systemowy',
            'system_prompt' => 'Jesteś architektem platformy Projekt-Emet.',
            'primary_model' => 'gpt-4o-mini',
            'is_active' => true,
        ]);

        $suite = AgentEvalSuite::create([
            'name' => 'Zestaw Golden Dataset v1',
            'slug' => 'zestaw-golden-dataset-v1',
            'description' => 'Weryfikacja wiedzy o warstwach pamięci i MCP',
            'agent_id' => $agent->id,
            'pass_threshold' => 0.50,
            'test_cases' => [
                [
                    'prompt' => 'Opisz warstwy pamięci krótkotrwałej i semantycznej w AgentHub.',
                    'expected_keywords' => ['odpowiedź', 'pamięci'],
                ],
                [
                    'prompt' => 'Jak działa protokół MCP JSON-RPC?',
                    'expected_keywords' => ['MCP', 'JSON-RPC'],
                ],
            ],
        ]);

        /** @var AgentEvaluationInterface $evalService */
        $evalService = app(AgentEvaluationInterface::class);

        $run = $evalService->runSuite(
            suite: $suite,
            modelA: 'gpt-4o-mini',
            modelB: 'claude-3-5-sonnet'
        );

        $this->assertContains($run->status, ['passed', 'failed']);
        $this->assertGreaterThan(0.0, $run->score_a);
        $this->assertNotNull($run->score_b);
        $this->assertNotEmpty($run->winner_model);
        $this->assertCount(2, $run->case_results);
        $this->assertArrayHasKey('model_a', $run->case_results[0]);
        $this->assertArrayHasKey('model_b', $run->case_results[0]);
    }

    public function test_scenario_time_travel_snapshots_and_fork_from_step(): void
    {
        $user = User::factory()->create();

        $scenario = Scenario::create([
            'name' => 'Potok Transformacji Danych',
            'slug' => 'potok-transformacji-danych',
            'status' => Scenario::STATUS_PUBLISHED,
            'created_by' => $user->id,
        ]);

        $graph = [
            'nodes' => [
                'n1' => [
                    'id' => 'n1',
                    'type' => 'start',
                    'name' => 'start',
                    'data' => ['label' => 'Start'],
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]],
                    ],
                ],
                'n2' => [
                    'id' => 'n2',
                    'type' => 'transform',
                    'name' => 'transform_step_1',
                    'data' => [
                        'key' => 'stage_one',
                        'value' => 'Prefix: {{initial_val}}',
                    ],
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n3', 'input' => 'input_1']]],
                    ],
                ],
                'n3' => [
                    'id' => 'n3',
                    'type' => 'transform',
                    'name' => 'transform_step_2',
                    'data' => [
                        'key' => 'final_result',
                        'value' => '{{stage_one}} -> Final: {{multiplier}}',
                    ],
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n4', 'input' => 'input_1']]],
                    ],
                ],
                'n4' => [
                    'id' => 'n4',
                    'type' => 'end',
                    'name' => 'end',
                    'data' => ['label' => 'Koniec'],
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

        $originalRunId = $engine->startRun($scenario->id, $version->id, [
            'initial_val' => 'Alpha',
            'multiplier' => 'x1',
        ]);

        $originalRun = ScenarioRun::findOrFail($originalRunId);
        $this->assertSame(ScenarioRun::STATUS_COMPLETED, $originalRun->status);
        $this->assertSame('Prefix: Alpha -> Final: x1', $originalRun->vars['final_result'] ?? null);
        $this->assertNotEmpty($originalRun->trace_id);

        // Inspekcja osi czasu Time-Travel
        $timeline = $engine->inspectRunTimeline($originalRunId);
        $this->assertSame($originalRunId, $timeline['run_id']);
        $this->assertCount(4, $timeline['steps']);
        $this->assertArrayHasKey('state_snapshot_before', $timeline['steps'][2]);
        $this->assertArrayHasKey('state_snapshot_after', $timeline['steps'][2]);

        // Forking stanu od kroku n3 z nadpisaniem zmiennej `multiplier` na `x99 (Forked)`
        $forkedRunId = $engine->forkAndReplayFromStep(
            sourceRunId: $originalRunId,
            fromNodeId: 'n3',
            overrideVars: ['multiplier' => 'x99 (Forked)']
        );

        $forkedRun = ScenarioRun::findOrFail($forkedRunId);
        $this->assertSame(ScenarioRun::STATUS_COMPLETED, $forkedRun->status);
        $this->assertSame($originalRunId, $forkedRun->parent_run_id);
        $this->assertSame('n3', $forkedRun->forked_from_node_id);
        // Zachowano `stage_one` z migawki kroku n2 ("Prefix: Alpha"), ale zastosowano nową zmienną `multiplier` w n3!
        $this->assertSame('Prefix: Alpha -> Final: x99 (Forked)', $forkedRun->vars['final_result'] ?? null);
    }

    public function test_opentelemetry_distributed_tracer_builds_waterfall_and_identifies_bottleneck_and_root_cause(): void
    {
        /** @var DistributedTracerInterface $tracer */
        $tracer = app(DistributedTracerInterface::class);

        $traceCtx = $tracer->startTrace('scenario.run:42', 'scenario-engine', ['scenario_id' => 42]);
        $traceId = $traceCtx['trace_id'];
        $rootSpanId = $traceCtx['root_span_id'];

        $ragSpan = $tracer->recordSpan(
            traceId: $traceId,
            name: 'memory.hybrid_search',
            serviceName: 'memory-service',
            durationMs: 65,
            parentSpanId: $rootSpanId,
            status: 'OK',
            attributes: ['top_k' => 5]
        );

        $llmSpan = $tracer->recordSpan(
            traceId: $traceId,
            name: 'llm.complete:gpt-4o',
            serviceName: 'llm-gateway',
            durationMs: 340,
            parentSpanId: $rootSpanId,
            status: 'ERROR',
            attributes: ['model' => 'gpt-4o'],
            errorMessage: 'Upstream rate limit 429'
        );

        $waterfall = $tracer->getWaterfallTrace($traceId);

        $this->assertSame($traceId, $waterfall['trace_id']);
        $this->assertSame(3, $waterfall['span_count']);
        $this->assertNotNull($waterfall['bottleneck_span']);
        $this->assertNotNull($waterfall['root_cause_error']);
        $this->assertSame($ragSpan->parent_span_id, $rootSpanId);
        $this->assertSame($llmSpan->parent_span_id, $rootSpanId);
    }

    public function test_evals_and_traces_ui_endpoints_are_accessible(): void
    {
        Permission::findOrCreate('agents.view', 'web');
        Permission::findOrCreate('agents.manage', 'web');
        Permission::findOrCreate('audit.view', 'web');
        Permission::findOrCreate('scenarios.view', 'web');
        Permission::findOrCreate('scenarios.manage', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['agents.view', 'agents.manage', 'audit.view', 'scenarios.view', 'scenarios.manage']);

        $evalsResponse = $this->actingAs($user)->get(route('agents.evals.index'));
        $evalsResponse->assertOk();
        $evalsResponse->assertSee('Podsystem Ewaluacji Jakości i Testów A/B Agentów');

        $tracesResponse = $this->actingAs($user)->get(route('logs.traces'));
        $tracesResponse->assertOk();
        $tracesResponse->assertSee('Rozproszony Tracing OpenTelemetry');
    }
}
