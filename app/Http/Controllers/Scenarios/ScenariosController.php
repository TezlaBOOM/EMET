<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Scenario;
use App\Models\ScenarioRun;
use App\Models\ScenarioTrigger;
use App\Models\ScenarioVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScenariosController extends Controller
{
    public function __construct(
        protected ScenarioEngineInterface $engine
    ) {}

    public function index(): View
    {
        $scenarios = Scenario::with(['currentVersion', 'creator'])
            ->latest()
            ->paginate(15);

        return view('module-scenarios::index', compact('scenarios'));
    }

    public function create(): View
    {
        return view('module-scenarios::create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        $scenario = Scenario::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => Scenario::STATUS_DRAFT,
            'created_by' => Auth::id() ?? 1,
        ]);

        // Początkowy graf ze start i end
        $initialGraph = [
            'nodes' => [
                'n1' => [
                    'id' => 'n1',
                    'type' => 'start',
                    'name' => 'start',
                    'data' => ['label' => 'Start Scenariusza'],
                    'pos_x' => 150,
                    'pos_y' => 200,
                    'outputs' => [
                        'output_1' => ['connections' => [['node' => 'n2', 'input' => 'input_1']]],
                    ],
                ],
                'n2' => [
                    'id' => 'n2',
                    'type' => 'end',
                    'name' => 'end',
                    'data' => ['label' => 'Koniec Scenariusza'],
                    'pos_x' => 550,
                    'pos_y' => 200,
                    'inputs' => [
                        'input_1' => ['connections' => [['node' => 'n1', 'output' => 'output_1']]],
                    ],
                ],
            ],
        ];

        $version = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => 1,
            'graph' => $initialGraph,
            'draft' => true,
            'changelog' => 'Wersja początkowa',
        ]);

        $scenario->update(['current_version_id' => $version->id]);

        return redirect()->route('scenarios.editor', $scenario)
            ->with('success', 'Scenariusz został pomyślnie utworzony.');
    }

    public function editor(Scenario $scenario): View
    {
        $scenario->load(['currentVersion', 'versions']);
        $agents = Agent::where('is_active', true)->get();

        return view('module-scenarios::editor', compact('scenario', 'agents'));
    }

    public function saveGraph(Request $request, Scenario $scenario): JsonResponse
    {
        $graph = $request->input('graph', []);

        $validation = $this->engine->validateGraph($graph);
        if (! $validation['is_valid']) {
            return response()->json([
                'success' => false,
                'errors' => $validation['errors'],
            ], 422);
        }

        $version = $scenario->currentVersion;
        if ($version && $version->draft) {
            $version->update(['graph' => $graph]);
        } else {
            $nextVersionNumber = ($scenario->versions()->max('version') ?? 0) + 1;
            $version = ScenarioVersion::create([
                'scenario_id' => $scenario->id,
                'version' => $nextVersionNumber,
                'graph' => $graph,
                'draft' => true,
                'changelog' => 'Zapisany szkic',
            ]);
            $scenario->update(['current_version_id' => $version->id]);
        }

        return response()->json([
            'success' => true,
            'version_id' => $version->id,
            'warnings' => $validation['warnings'],
        ]);
    }

    public function publish(Request $request, Scenario $scenario): JsonResponse
    {
        $version = $scenario->currentVersion;
        if (! $version) {
            return response()->json(['success' => false, 'error' => 'Brak wersji do publikacji'], 400);
        }

        $validation = $this->engine->validateGraph($version->graph ?? []);
        if (! $validation['is_valid']) {
            return response()->json([
                'success' => false,
                'errors' => $validation['errors'],
            ], 422);
        }

        $version->update([
            'draft' => false,
            'published_by' => Auth::id(),
            'published_at' => now(),
        ]);

        $scenario->update(['status' => Scenario::STATUS_PUBLISHED]);

        return response()->json([
            'success' => true,
            'message' => "Wersja v{$version->version} została pomyślnie opublikowana.",
        ]);
    }

    public function run(Request $request, Scenario $scenario): JsonResponse
    {
        $input = $request->input('input', []);
        $runId = $this->engine->startRun($scenario->id, $scenario->current_version_id, $input, 'manual');

        return response()->json([
            'success' => true,
            'run_id' => $runId,
        ]);
    }

    public function runStatus(ScenarioRun $run): JsonResponse
    {
        $run->load('steps');

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'vars' => $run->vars,
            'steps' => $run->steps,
            'error' => $run->error,
        ]);
    }

    public function resumeRun(Request $request, ScenarioRun $run): JsonResponse
    {
        $humanInput = $request->input('human_input', []);
        $nodeId = $request->input('node_id');

        $resumed = $this->engine->resumeRun($run->id, $nodeId, $humanInput);

        return response()->json([
            'success' => $resumed,
        ]);
    }

    public function webhookTrigger(Request $request, string $token): JsonResponse
    {
        $tokenHash = hash('sha256', $token);
        $trigger = ScenarioTrigger::where('token_hash', $tokenHash)
            ->where('enabled', true)
            ->first();

        if (! $trigger) {
            return response()->json(['error' => 'Nieprawidłowy token wyzwalacza webhooka'], 403);
        }

        $runId = $this->engine->startRun(
            scenarioId: $trigger->scenario_id,
            versionId: null,
            input: $request->all(),
            triggerType: 'webhook'
        );

        $trigger->update(['last_fired_at' => now()]);

        return response()->json([
            'success' => true,
            'run_id' => $runId,
        ], 202);
    }
}
