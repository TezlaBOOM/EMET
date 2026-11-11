<?php

declare(strict_types=1);

namespace App\Services\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Telemetry\DistributedTracerInterface;
use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Models\Agent;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioRunStep;
use App\Models\ScenarioVersion;
use App\Services\AgentContextBuilder;
use App\Services\TelemetryCollector\DistributedTracerService;
use InvalidArgumentException;
use Throwable;

class ScenarioEngine implements ScenarioEngineInterface
{
    protected DistributedTracerInterface $tracer;

    public function __construct(
        protected ScenarioGraphValidator $validator,
        protected ScenarioConditionEvaluator $conditionEvaluator,
        protected TelemetryCollectorInterface $telemetry,
        protected AgentContextBuilder $contextBuilder,
        ?DistributedTracerInterface $tracer = null,
    ) {
        $this->tracer = $tracer ?? new DistributedTracerService;
    }

    public function validateGraph(array $graphJson): array
    {
        return $this->validator->validate($graphJson);
    }

    public function startRun(int $scenarioId, ?int $versionId, array $input, string $triggerType = 'manual'): int
    {
        $scenario = Scenario::with('currentVersion')->findOrFail($scenarioId);
        $version = $versionId ? ScenarioVersion::findOrFail($versionId) : $scenario->currentVersion;

        if (! $version) {
            throw new InvalidArgumentException("Scenariusz {$scenario->name} nie posiada opublikowanej wersji.");
        }

        $trace = $this->tracer->startTrace(
            rootName: "scenario.run:{$scenario->slug}",
            serviceName: 'scenario-engine',
            attributes: [
                'scenario_id' => $scenario->id,
                'version' => $version->version,
                'trigger' => $triggerType,
            ]
        );

        $run = ScenarioRun::create([
            'scenario_id' => $scenario->id,
            'version_id' => $version->id,
            'status' => ScenarioRun::STATUS_RUNNING,
            'trigger_type' => $triggerType,
            'input' => $input,
            'vars' => $input,
            'trace_id' => $trace['trace_id'],
            'state_snapshots' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'cost' => 0.0,
            'started_at' => now(),
        ]);

        $this->telemetry->recordEvent('scenario.started', null, (string) $run->id, [
            'scenario_id' => $scenario->id,
            'version' => $version->version,
            'trigger' => $triggerType,
            'trace_id' => $trace['trace_id'],
        ]);

        // Znalezienie węzła start
        $graph = $version->graph ?? [];
        $nodes = $graph['nodes'] ?? ($graph['drawflow']['Home']['data'] ?? []);

        $startNodeId = null;
        foreach ($nodes as $key => $node) {
            $type = $node['type'] ?? ($node['name'] ?? '');
            if ($type === 'start') {
                $startNodeId = (string) ($node['id'] ?? $key);
                break;
            }
        }

        if ($startNodeId) {
            $this->executeNode($run->id, $startNodeId);
        }

        return $run->id;
    }

    public function executeNode(int $runId, string $nodeId): array
    {
        $stepStartTime = microtime(true);
        $run = ScenarioRun::with('version')->findOrFail($runId);

        if ($run->status === ScenarioRun::STATUS_CANCELLED) {
            return ['status' => 'cancelled', 'output' => null, 'error' => 'Run został anulowany'];
        }

        $graph = $run->version->graph ?? [];
        $nodes = $graph['nodes'] ?? ($graph['drawflow']['Home']['data'] ?? []);

        $nodeData = $nodes[$nodeId] ?? null;
        if (! $nodeData) {
            foreach ($nodes as $n) {
                if ((string) ($n['id'] ?? '') === (string) $nodeId) {
                    $nodeData = $n;
                    break;
                }
            }
        }

        if (! $nodeData) {
            return ['status' => 'failed', 'output' => null, 'error' => "Węzeł {$nodeId} nie został odnaleziony w grafie"];
        }

        $nodeType = (string) ($nodeData['type'] ?? ($nodeData['name'] ?? 'transform'));
        $snapshotBefore = is_array($run->vars) ? $run->vars : [];

        $step = ScenarioRunStep::firstOrCreate([
            'run_id' => $run->id,
            'node_id' => $nodeId,
        ], [
            'node_type' => $nodeType,
            'status' => ScenarioRunStep::STATUS_RUNNING,
            'input' => $snapshotBefore,
            'state_snapshot_before' => $snapshotBefore,
            'started_at' => now(),
        ]);

        $status = ScenarioRunStep::STATUS_SUCCESS;
        $output = null;
        $error = null;

        try {
            switch ($nodeType) {
                case 'start':
                    $output = $run->input;
                    break;

                case 'condition':
                    $expr = $nodeData['data']['expression'] ?? 'true';
                    $evalResult = $this->conditionEvaluator->evaluate($expr, $snapshotBefore);
                    $output = ['condition_met' => $evalResult];
                    break;

                case 'human':
                    // Wstrzymanie wykonania w oczekiwaniu na akceptację człowieka
                    $step->update([
                        'status' => ScenarioRunStep::STATUS_WAITING,
                        'input' => $snapshotBefore,
                        'state_snapshot_before' => $snapshotBefore,
                    ]);
                    $run->update(['status' => ScenarioRun::STATUS_PAUSED]);

                    return ['status' => 'waiting', 'output' => null, 'error' => null];

                case 'agent':
                    $agentId = $nodeData['data']['agent_id'] ?? null;
                    if ($agentId) {
                        $agent = Agent::find($agentId);
                        $output = [
                            'agent_id' => $agentId,
                            'agent_name' => $agent?->name,
                            'result' => "Odpowiedź od {$agent?->name}",
                        ];
                    }
                    break;

                case 'transform':
                    $customPayload = is_array($nodeData['data']['set'] ?? null) ? $nodeData['data']['set'] : [];
                    if (isset($nodeData['data']['key'], $nodeData['data']['value'])) {
                        $rawVal = (string) $nodeData['data']['value'];
                        foreach ($snapshotBefore as $varKey => $varVal) {
                            if (is_scalar($varVal)) {
                                $rawVal = str_replace('{{'.$varKey.'}}', (string) $varVal, $rawVal);
                            }
                        }
                        $customPayload[(string) $nodeData['data']['key']] = $rawVal;
                    }
                    $output = array_merge(['type' => 'transform', 'processed' => true], $customPayload);
                    break;

                case 'end':
                    $output = $snapshotBefore;
                    $run->update([
                        'status' => ScenarioRun::STATUS_SUCCESS,
                        'finished_at' => now(),
                        'output' => $output,
                    ]);
                    break;

                default:
                    $output = ['type' => $nodeType, 'processed' => true];
            }
        } catch (Throwable $e) {
            $status = ScenarioRunStep::STATUS_FAILED;
            $error = $e->getMessage();
            $run->update([
                'status' => ScenarioRun::STATUS_FAILED,
                'error' => $error,
                'finished_at' => now(),
            ]);
        }

        // Aktualizacja zmiennych kontekstu runu i zapis migawki po kroku (Time-Travel Snapshot)
        $snapshotAfter = $snapshotBefore;
        if ($output && is_array($output)) {
            $snapshotAfter = array_merge($snapshotBefore, $output);
        }

        $durationMs = max(1, (int) round((microtime(true) - $stepStartTime) * 1000));

        $step->update([
            'status' => $status,
            'input' => $snapshotBefore,
            'output' => $output,
            'error' => $error,
            'state_snapshot_before' => $snapshotBefore,
            'state_snapshot_after' => $snapshotAfter,
            'duration_ms' => $durationMs,
            'finished_at' => now(),
        ]);

        $allSnapshots = is_array($run->state_snapshots) ? $run->state_snapshots : [];
        $allSnapshots[$nodeId] = [
            'node_type' => $nodeType,
            'before' => $snapshotBefore,
            'after' => $snapshotAfter,
            'status' => $status,
            'duration_ms' => $durationMs,
        ];

        $run->update([
            'vars' => $snapshotAfter,
            'state_snapshots' => $allSnapshots,
        ]);

        // Rejestracja spanu OpenTelemetry (#11)
        if (! empty($run->trace_id)) {
            $this->tracer->recordSpan(
                traceId: (string) $run->trace_id,
                name: "node:{$nodeId} ({$nodeType})",
                serviceName: $nodeType === 'agent' ? 'llm-gateway' : ($nodeType === 'memory' ? 'memory-service' : 'scenario-engine'),
                durationMs: $durationMs,
                parentSpanId: null,
                status: $status === ScenarioRunStep::STATUS_FAILED ? 'ERROR' : 'OK',
                attributes: [
                    'run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                ],
                errorMessage: $error,
            );
        }

        // Przejście do następnego węzła jeśli sukces i nie jest to węzeł end
        if ($status === ScenarioRunStep::STATUS_SUCCESS && $nodeType !== 'end') {
            $nextNodes = $this->findNextNodes($nodeData, $nodes, $output);
            foreach ($nextNodes as $nextId) {
                $this->executeNode($run->id, $nextId);
            }
        }

        return [
            'status' => $status,
            'output' => $output,
            'error' => $error,
        ];
    }

    public function pauseRun(int $runId): bool
    {
        $run = ScenarioRun::findOrFail($runId);
        $run->update(['status' => ScenarioRun::STATUS_PAUSED]);

        return true;
    }

    public function resumeRun(int $runId, ?string $nodeId = null, array $humanInput = []): bool
    {
        $run = ScenarioRun::findOrFail($runId);

        // Scalenie danych wejściowych od człowieka
        $vars = $run->vars ?? [];
        if (! empty($humanInput)) {
            $vars = array_merge($vars, ['human_input' => $humanInput]);
            $run->update(['vars' => $vars]);
        }

        $run->update(['status' => ScenarioRun::STATUS_RUNNING]);

        // Jeśli podano nodeId węzła oczekującego, wznawiamy go
        if ($nodeId) {
            $step = ScenarioRunStep::where('run_id', $run->id)
                ->where('node_id', $nodeId)
                ->first();

            if ($step && $step->status === ScenarioRunStep::STATUS_WAITING) {
                $step->update([
                    'status' => ScenarioRunStep::STATUS_SUCCESS,
                    'output' => $humanInput,
                    'state_snapshot_after' => $vars,
                    'finished_at' => now(),
                ]);

                // Przechodzimy do następnych węzłów
                $graph = $run->version->graph ?? [];
                $nodes = $graph['nodes'] ?? ($graph['drawflow']['Home']['data'] ?? []);
                $nodeData = $nodes[$nodeId] ?? null;

                if ($nodeData) {
                    $nextNodes = $this->findNextNodes($nodeData, $nodes, $humanInput);
                    foreach ($nextNodes as $nextId) {
                        $this->executeNode($run->id, $nextId);
                    }
                }
            }
        }

        return true;
    }

    public function stopRun(int $runId): bool
    {
        $run = ScenarioRun::findOrFail($runId);
        $run->update([
            'status' => ScenarioRun::STATUS_CANCELLED,
            'finished_at' => now(),
        ]);

        return true;
    }

    public function forkAndReplayFromStep(int $sourceRunId, string $fromNodeId, array $overrideVars = []): int
    {
        $sourceRun = ScenarioRun::with(['steps', 'scenario'])->findOrFail($sourceRunId);
        $targetStep = $sourceRun->steps->firstWhere('node_id', $fromNodeId);

        // Pobieramy migawkę zmiennych sprzed wykonania wybranego węzła
        $baseVars = [];
        if ($targetStep && is_array($targetStep->state_snapshot_before)) {
            $baseVars = $targetStep->state_snapshot_before;
        } elseif (is_array($sourceRun->state_snapshots[$fromNodeId]['before'] ?? null)) {
            $baseVars = $sourceRun->state_snapshots[$fromNodeId]['before'];
        } else {
            $baseVars = is_array($sourceRun->vars) ? $sourceRun->vars : [];
        }

        $forkedVars = array_merge($baseVars, $overrideVars);

        $trace = $this->tracer->startTrace(
            rootName: "scenario.fork:{$sourceRun->scenario?->slug}@{$fromNodeId}",
            serviceName: 'scenario-engine',
            attributes: [
                'parent_run_id' => $sourceRun->id,
                'forked_from_node_id' => $fromNodeId,
            ]
        );

        $forkedRun = ScenarioRun::create([
            'scenario_id' => $sourceRun->scenario_id,
            'version_id' => $sourceRun->version_id,
            'parent_run_id' => $sourceRun->id,
            'forked_from_node_id' => $fromNodeId,
            'trace_id' => $trace['trace_id'],
            'status' => ScenarioRun::STATUS_RUNNING,
            'trigger_type' => 'time_travel_fork',
            'input' => $forkedVars,
            'vars' => $forkedVars,
            'state_snapshots' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'cost' => 0.0,
            'started_at' => now(),
        ]);

        // Kopiujemy wcześniejsze kroki sprzed punktu rozwidlenia jako odtworzone z migawki
        foreach ($sourceRun->steps as $oldStep) {
            if ((string) $oldStep->node_id === (string) $fromNodeId) {
                break;
            }

            ScenarioRunStep::create([
                'run_id' => $forkedRun->id,
                'node_id' => $oldStep->node_id,
                'node_type' => $oldStep->node_type,
                'status' => ScenarioRunStep::STATUS_SKIPPED,
                'input' => $oldStep->input,
                'output' => $oldStep->output,
                'state_snapshot_before' => $oldStep->state_snapshot_before,
                'state_snapshot_after' => $oldStep->state_snapshot_after,
                'duration_ms' => 0,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        // Wznawiamy wykonanie dokładnie od wskazanego węzła z nowym stanem zmiennych!
        $this->executeNode($forkedRun->id, $fromNodeId);

        return $forkedRun->id;
    }

    public function inspectRunTimeline(int $runId): array
    {
        $run = ScenarioRun::with('steps')->findOrFail($runId);
        $timeline = [];

        foreach ($run->steps as $step) {
            $before = is_array($step->state_snapshot_before) ? $step->state_snapshot_before : (is_array($step->input) ? $step->input : []);
            $after = is_array($step->state_snapshot_after) ? $step->state_snapshot_after : [];

            $changedKeys = [];
            foreach ($after as $k => $v) {
                if (! array_key_exists($k, $before) || $before[$k] !== $v) {
                    $changedKeys[$k] = $v;
                }
            }

            $timeline[] = [
                'step_id' => $step->id,
                'node_id' => $step->node_id,
                'node_type' => $step->node_type,
                'status' => $step->status,
                'duration_ms' => $step->duration_ms,
                'state_before' => $before,
                'state_after' => $after,
                'state_snapshot_before' => $before,
                'state_snapshot_after' => $after,
                'diff_changed' => $changedKeys,
                'error' => $step->error,
            ];
        }

        return [
            'run_id' => $run->id,
            'parent_run_id' => $run->parent_run_id,
            'forked_from_node_id' => $run->forked_from_node_id,
            'trace_id' => $run->trace_id,
            'status' => $run->status,
            'timeline' => $timeline,
            'steps' => $timeline,
        ];
    }

    /**
     * Wyszukuje kolejne połączone węzły grafu.
     *
     * @param  array<string, mixed>  $currentNode
     * @param  array<string, mixed>  $allNodes
     * @return array<int, string>
     */
    protected function findNextNodes(array $currentNode, array $allNodes, mixed $output): array
    {
        $next = [];

        // Obsługa wyjść z Drawflow
        $outputs = $currentNode['outputs'] ?? [];

        // Jeśli węzeł to condition, wybieramy output_1 (true) lub output_2 (false)
        $nodeType = $currentNode['type'] ?? ($currentNode['name'] ?? '');
        if ($nodeType === 'condition' && is_array($output)) {
            $isMet = $output['condition_met'] ?? false;
            $chosenOutputKey = $isMet ? 'output_1' : 'output_2';

            if (isset($outputs[$chosenOutputKey])) {
                foreach ($outputs[$chosenOutputKey]['connections'] ?? [] as $conn) {
                    $next[] = (string) $conn['node'];
                }

                return $next;
            }
        }

        foreach ($outputs as $outKey => $outData) {
            foreach ($outData['connections'] ?? [] as $conn) {
                $next[] = (string) $conn['node'];
            }
        }

        return array_unique($next);
    }
}
