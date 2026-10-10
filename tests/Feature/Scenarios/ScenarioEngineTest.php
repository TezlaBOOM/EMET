<?php

declare(strict_types=1);

namespace Tests\Feature\Scenarios;

use App\Models\Agent;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioRunStep;
use App\Models\ScenarioVersion;
use App\Models\User;
use App\Services\Scenarios\ScenarioConditionEvaluator;
use App\Services\Scenarios\ScenarioEngine;
use App\Services\Scenarios\ScenarioGraphValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Scenarios\Config\ScenariosConfigSection;
use Tests\TestCase;

class ScenarioEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Agent $agent;

    protected ScenarioEngine $engine;

    protected ScenarioGraphValidator $validator;

    protected ScenarioConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->agent = Agent::create([
            'name' => 'Scenario Agent',
            'slug' => 'scenario-agent',
            'primary_model' => 'gpt-4o',
        ]);

        $this->engine = app(ScenarioEngine::class);
        $this->validator = app(ScenarioGraphValidator::class);
        $this->evaluator = app(ScenarioConditionEvaluator::class);
    }

    public function test_graph_validator_detects_valid_and_invalid_structures(): void
    {
        // 1. Poprawny graf
        $validGraph = [
            'nodes' => [
                'n1' => ['id' => 'n1', 'type' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'n2']]]]],
                'n2' => ['id' => 'n2', 'type' => 'agent', 'outputs' => ['output_1' => ['connections' => [['node' => 'n3']]]]],
                'n3' => ['id' => 'n3', 'type' => 'end', 'outputs' => []],
            ],
        ];

        $resValid = $this->validator->validate($validGraph);
        $this->assertTrue($resValid['is_valid']);
        $this->assertEmpty($resValid['errors']);

        // 2. Graf bez węzła start
        $noStartGraph = [
            'nodes' => [
                'n1' => ['id' => 'n1', 'type' => 'agent', 'outputs' => []],
                'n2' => ['id' => 'n2', 'type' => 'end', 'outputs' => []],
            ],
        ];
        $resNoStart = $this->validator->validate($noStartGraph);
        $this->assertFalse($resNoStart['is_valid']);
        $this->assertStringContainsString('start', $resNoStart['errors'][0]);

        // 3. Graf z cyklem (n1 -> n2 -> n1)
        $cyclicGraph = [
            'nodes' => [
                'n1' => ['id' => 'n1', 'type' => 'start', 'outputs' => ['output_1' => ['connections' => [['node' => 'n2']]]]],
                'n2' => ['id' => 'n2', 'type' => 'agent', 'outputs' => ['output_1' => ['connections' => [['node' => 'n1']]]]],
                'n3' => ['id' => 'n3', 'type' => 'end', 'outputs' => []],
            ],
        ];
        $resCyclic = $this->validator->validate($cyclicGraph);
        $this->assertFalse($resCyclic['is_valid']);
        $this->assertStringContainsString('cykl', $resCyclic['errors'][0]);
    }

    public function test_condition_evaluator_evaluates_safely(): void
    {
        $this->assertTrue($this->evaluator->evaluate("status == 'success'", ['status' => 'success']));
        $this->assertFalse($this->evaluator->evaluate("status == 'failed'", ['status' => 'success']));
        $this->assertTrue($this->evaluator->evaluate('score >= 80 and active == true', ['score' => 95, 'active' => true]));
        $this->assertFalse($this->evaluator->evaluate('score >= 80 and active == true', ['score' => 60, 'active' => true]));
    }

    public function test_scenario_execution_with_agent_and_condition_nodes(): void
    {
        $graph = [
            'nodes' => [
                'n1' => [
                    'id' => 'n1',
                    'type' => 'start',
                    'outputs' => ['output_1' => ['connections' => [['node' => 'n2']]]],
                ],
                'n2' => [
                    'id' => 'n2',
                    'type' => 'agent',
                    'data' => ['agent_id' => $this->agent->id],
                    'outputs' => ['output_1' => ['connections' => [['node' => 'n3']]]],
                ],
                'n3' => [
                    'id' => 'n3',
                    'type' => 'condition',
                    'data' => ['expression' => "agent_id == {$this->agent->id}"],
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n4']]],
                        'output_2' => ['connections' => []],
                    ],
                ],
                'n4' => [
                    'id' => 'n4',
                    'type' => 'end',
                    'outputs' => [],
                ],
            ],
        ];

        $scenario = Scenario::create([
            'name' => 'Agent Condition Test',
            'slug' => 'agent-condition-test',
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $version = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => 1,
            'graph' => $graph,
            'draft' => false,
        ]);
        $scenario->update(['current_version_id' => $version->id]);

        $runId = $this->engine->startRun($scenario->id, $version->id, ['initial' => 'data']);
        $this->assertIsInt($runId);

        $run = ScenarioRun::with('steps')->findOrFail($runId);
        $this->assertSame(ScenarioRun::STATUS_SUCCESS, $run->status);
        $this->assertCount(4, $run->steps);
    }

    public function test_scenario_human_approval_step_pauses_and_resumes(): void
    {
        $graph = [
            'nodes' => [
                'n1' => [
                    'id' => 'n1',
                    'type' => 'start',
                    'outputs' => ['output_1' => ['connections' => [['node' => 'n2']]]],
                ],
                'n2' => [
                    'id' => 'n2',
                    'type' => 'human',
                    'outputs' => ['output_1' => ['connections' => [['node' => 'n3']]]],
                ],
                'n3' => [
                    'id' => 'n3',
                    'type' => 'end',
                    'outputs' => [],
                ],
            ],
        ];

        $scenario = Scenario::create([
            'name' => 'Human Approval Test',
            'slug' => 'human-approval-test',
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $version = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => 1,
            'graph' => $graph,
            'draft' => false,
        ]);
        $scenario->update(['current_version_id' => $version->id]);

        // Start run - powinien zatrzymać się na kroku human
        $runId = $this->engine->startRun($scenario->id, $version->id, []);
        $run = ScenarioRun::findOrFail($runId);

        $this->assertSame(ScenarioRun::STATUS_PAUSED, $run->status);

        $humanStep = ScenarioRunStep::where('run_id', $runId)
            ->where('node_id', 'n2')
            ->first();
        $this->assertNotNull($humanStep);
        $this->assertSame(ScenarioRunStep::STATUS_WAITING, $humanStep->status);

        // Akceptacja człowieka
        $resumed = $this->engine->resumeRun($runId, 'n2', ['approved' => true, 'comment' => 'Zgoda']);
        $this->assertTrue($resumed);

        $run->refresh();
        $this->assertSame(ScenarioRun::STATUS_SUCCESS, $run->status);
        $this->assertTrue($run->vars['human_input']['approved']);
    }

    public function test_scenarios_config_section(): void
    {
        $section = app(ScenariosConfigSection::class);
        $this->assertSame('scenarios', $section->key());
        $this->assertSame(['agents', 'skills'], $section->dependsOn());

        $exported = $section->export([]);
        $this->assertIsArray($exported['records']);
        $this->assertTrue($section->validate($exported)['is_valid']);
    }
}
