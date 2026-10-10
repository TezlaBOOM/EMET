<?php

declare(strict_types=1);

namespace App\Services\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Telemetry\TelemetryCollectorInterface;
use App\Models\Agent;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioRunStep;
use App\Models\ScenarioVersion;
use App\Services\AgentContextBuilder;
use InvalidArgumentException;
use Throwable;

class ScenarioEngine implements ScenarioEngineInterface
{
    public function __construct(
        protected ScenarioGraphValidator $validator,
        protected ScenarioConditionEvaluator $conditionEvaluator,
        protected TelemetryCollectorInterface $telemetry,
        protected AgentContextBuilder $contextBuilder
    ) {}

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

        $run = ScenarioRun::create([
            'scenario_id' => $scenario->id,
            'version_id' => $version->id,
            'status' => ScenarioRun::STATUS_RUNNING,
            'trigger_type' => $triggerType,
            'input' => $input,
            'vars' => $input,
            'tokens_in' => 0,
            'tokens_out' => 0,
            'cost' => 0.0,
            'started_at' => now(),
        ]);

        $this->telemetry->recordEvent('scenario.started', null, (string) $run->id, [
            'scenario_id' => $scenario->id,
            'version' => $version->version,
            'trigger' => $triggerType,
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

        $nodeType = $nodeData['type'] ?? ($nodeData['name'] ?? 'transform');
        $step = ScenarioRunStep::firstOrCreate([
            'run_id' => $run->id,
            'node_id' => $nodeId,
        ], [
            'node_type' => $nodeType,
            'status' => ScenarioRunStep::STATUS_RUNNING,
            'input' => $run->vars,
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
                    $evalResult = $this->conditionEvaluator->evaluate($expr, $run->vars ?? []);
                    $output = ['condition_met' => $evalResult];
                    break;

                case 'human':
                    // Wstrzymanie wykonania w oczekiwaniu na akceptację człowieka
                    $step->update([
                        'status' => ScenarioRunStep::STATUS_WAITING,
                        'input' => $run->vars,
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

                case 'end':
                    $output = $run->vars;
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

        $step->update([
            'status' => $status,
            'output' => $output,
            'error' => $error,
            'finished_at' => now(),
        ]);

        // Aktualizacja zmiennych runu
        if ($output && is_array($output)) {
            $currentVars = $run->vars ?? [];
            $run->update(['vars' => array_merge($currentVars, $output)]);
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
