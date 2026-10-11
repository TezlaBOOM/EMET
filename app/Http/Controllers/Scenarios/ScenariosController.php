<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scenarios;

use App\Contracts\Scenarios\ScenarioEngineInterface;
use App\Contracts\Scenarios\ScenarioSchedulerInterface;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ScenariosController extends Controller
{
    public function __construct(
        protected ScenarioEngineInterface $engine,
        protected ScenarioSchedulerInterface $scheduler
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

        $scenario = DB::transaction(function () use ($validated) {
            $baseSlug = Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;
            while (Scenario::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $scenario = Scenario::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'status' => Scenario::STATUS_DRAFT,
                'created_by' => Auth::id() ?? 1,
            ]);

            // Początkowy kanoniczny graf ze start i end
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

            return $scenario;
        });

        return redirect()->route('scenarios.editor', $scenario)
            ->with('success', __('scenarios.created_success', ['default' => 'Scenariusz został pomyślnie utworzony.']));
    }

    public function edit(Scenario $scenario): View
    {
        $scenario->load(['currentVersion', 'versions']);

        return view('module-scenarios::edit', compact('scenario'));
    }

    public function update(Request $request, Scenario $scenario): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);

        $scenario->update($validated);

        return redirect()->route('scenarios.edit', $scenario)
            ->with('success', 'Metadane scenariusza zostały pomyślnie zaktualizowane.');
    }

    public function restoreVersion(Scenario $scenario, ScenarioVersion $version): RedirectResponse
    {
        if ($version->scenario_id !== $scenario->id) {
            abort(403, 'Wersja nie należy do tego scenariusza.');
        }

        $nextVersion = ($scenario->versions()->max('version') ?? 0) + 1;
        $restored = ScenarioVersion::create([
            'scenario_id' => $scenario->id,
            'version' => $nextVersion,
            'graph' => $version->graph,
            'draft' => false,
            'changelog' => "Przywrócono z wersji v{$version->version}",
            'published_by' => Auth::id(),
            'published_at' => now(),
        ]);

        $scenario->update([
            'current_version_id' => $restored->id,
            'status' => Scenario::STATUS_PUBLISHED,
        ]);

        return redirect()->route('scenarios.edit', $scenario)
            ->with('success', "Pomyślnie przywrócono graf z wersji v{$version->version} jako nową wersję aktywną v{$nextVersion}.");
    }

    public function acquireLock(Request $request, Scenario $scenario): JsonResponse|RedirectResponse
    {
        $scenario->update([
            'locked_by' => Auth::id(),
            'locked_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Przejęto blokadę edycji scenariusza.');
    }

    public function releaseLock(Request $request, Scenario $scenario): JsonResponse|RedirectResponse
    {
        if ($scenario->locked_by === Auth::id() || Auth::user()?->hasRole('admin')) {
            $scenario->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Zwolniono blokadę edycji.');
    }

    public function editor(Scenario $scenario): View
    {
        $scenario->load(['currentVersion', 'versions', 'lockedByUser']);
        $agents = Agent::where('is_active', true)->get();

        $isLocked = $scenario->isLockedByOtherUser();
        $lockedUser = $isLocked ? $scenario->lockedByUser : null;

        if (! $isLocked && Auth::check()) {
            $scenario->update([
                'locked_by' => Auth::id(),
                'locked_at' => now(),
            ]);
        }

        return view('module-scenarios::editor', compact('scenario', 'agents', 'isLocked', 'lockedUser'));
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

    public function triggers(Scenario $scenario): View
    {
        $scenario->load(['triggers' => fn ($q) => $q->latest()]);

        return view('module-scenarios::triggers.index', [
            'scenario' => $scenario,
            'triggers' => $scenario->triggers,
        ]);
    }

    public function storeTrigger(Request $request, Scenario $scenario): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'schedule_type' => ['required', 'string', 'in:once,interval,daily,weekly,monthly,cron'],
            'timezone' => ['nullable', 'string'],
            'max_runs' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['nullable', 'array'],
            'run_at' => ['nullable', 'string'],
            'interval_minutes' => ['nullable', 'integer', 'min:1'],
            'time' => ['nullable', 'string'],
            'days' => ['nullable', 'array'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'cron_expression' => ['nullable', 'string'],
        ]);

        $scheduleType = $validated['schedule_type'];
        $timezone = $validated['timezone'] ?? 'Europe/Warsaw';

        $schedule = $validated['schedule'] ?? [];
        if (empty($schedule)) {
            $schedule = match ($scheduleType) {
                'once' => ['run_at' => $request->input('run_at')],
                'interval' => ['interval_minutes' => (int) $request->input('interval_minutes', 60)],
                'daily' => ['time' => $request->input('time', '09:00')],
                'weekly' => [
                    'days' => $request->input('days', ['monday']),
                    'time' => $request->input('time', '09:00'),
                ],
                'monthly' => [
                    'day_of_month' => (int) $request->input('day_of_month', 1),
                    'time' => $request->input('time', '09:00'),
                ],
                'cron' => ['cron_expression' => $request->input('cron_expression', '0 * * * *')],
                default => [],
            };
        }

        $nextRunAt = $this->scheduler->calculateNextRun($scheduleType, $schedule, $timezone);

        $scenario->triggers()->create([
            'type' => 'scheduled',
            'name' => $validated['name'],
            'schedule_type' => $scheduleType,
            'schedule' => $schedule,
            'timezone' => $timezone,
            'enabled' => true,
            'state' => ScenarioTrigger::STATE_ACTIVE,
            'next_run_at' => $nextRunAt,
            'max_runs' => $validated['max_runs'] ?? null,
            'input' => $request->input('input', []),
        ]);

        return redirect()->route('scenarios.triggers.index', $scenario)
            ->with('success', 'Harmonogram został pomyślnie utworzony.');
    }

    public function updateTrigger(Request $request, Scenario $scenario, ScenarioTrigger $trigger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'schedule_type' => ['required', 'string', 'in:once,interval,daily,weekly,monthly,cron'],
            'timezone' => ['nullable', 'string'],
            'max_runs' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['nullable', 'array'],
            'run_at' => ['nullable', 'string'],
            'interval_minutes' => ['nullable', 'integer', 'min:1'],
            'time' => ['nullable', 'string'],
            'days' => ['nullable', 'array'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'cron_expression' => ['nullable', 'string'],
        ]);

        $scheduleType = $validated['schedule_type'];
        $timezone = $validated['timezone'] ?? $trigger->timezone ?? 'Europe/Warsaw';
        $schedule = $validated['schedule'] ?? [];

        if (empty($schedule)) {
            $schedule = match ($scheduleType) {
                'once' => ['run_at' => $request->input('run_at')],
                'interval' => ['interval_minutes' => (int) $request->input('interval_minutes', 60)],
                'daily' => ['time' => $request->input('time', '09:00')],
                'weekly' => [
                    'days' => $request->input('days', ['monday']),
                    'time' => $request->input('time', '09:00'),
                ],
                'monthly' => [
                    'day_of_month' => (int) $request->input('day_of_month', 1),
                    'time' => $request->input('time', '09:00'),
                ],
                'cron' => ['cron_expression' => $request->input('cron_expression', '0 * * * *')],
                default => $trigger->schedule ?? [],
            };
        }

        $nextRunAt = $this->scheduler->calculateNextRun($scheduleType, $schedule, $timezone);

        $trigger->update([
            'name' => $validated['name'],
            'schedule_type' => $scheduleType,
            'schedule' => $schedule,
            'timezone' => $timezone,
            'max_runs' => $validated['max_runs'] ?? null,
            'next_run_at' => $nextRunAt,
        ]);

        return redirect()->route('scenarios.triggers.index', $scenario)
            ->with('success', 'Harmonogram został zaktualizowany.');
    }

    public function destroyTrigger(Scenario $scenario, ScenarioTrigger $trigger): RedirectResponse
    {
        $trigger->delete();

        return redirect()->route('scenarios.triggers.index', $scenario)
            ->with('success', 'Harmonogram został usunięty.');
    }

    public function toggleTrigger(Request $request, Scenario $scenario, ScenarioTrigger $trigger): JsonResponse|RedirectResponse
    {
        $newEnabled = ! $trigger->enabled;
        $trigger->enabled = $newEnabled;

        if ($newEnabled) {
            $trigger->state = ScenarioTrigger::STATE_ACTIVE;
            if (! $trigger->next_run_at) {
                $trigger->next_run_at = $this->scheduler->calculateNextRun(
                    $trigger->schedule_type,
                    $trigger->schedule ?? [],
                    $trigger->timezone ?? 'Europe/Warsaw'
                );
            }
        } else {
            $trigger->state = ScenarioTrigger::STATE_PAUSED;
        }

        $trigger->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'enabled' => $trigger->enabled,
                'state' => $trigger->state,
            ]);
        }

        return redirect()->route('scenarios.triggers.index', $scenario)
            ->with('success', 'Stan harmonogramu został zmieniony.');
    }

    public function runTriggerNow(Request $request, Scenario $scenario, ScenarioTrigger $trigger): JsonResponse|RedirectResponse
    {
        try {
            $run = $this->scheduler->triggerNow($trigger);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'run_id' => $run->id,
                    'message' => 'Scenariusz został pomyślnie uruchomiony.',
                ]);
            }

            return redirect()->route('scenarios.triggers.index', $scenario)
                ->with('success', "Wyzwalacz został uruchomiony (ID uruchomienia: {$run->id}).");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500);
            }

            return redirect()->route('scenarios.triggers.index', $scenario)
                ->with('error', 'Błąd uruchomienia: '.$e->getMessage());
        }
    }

    public function previewSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_type' => ['required', 'string', 'in:once,interval,daily,weekly,monthly,cron'],
            'schedule' => ['required', 'array'],
            'timezone' => ['nullable', 'string'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $runs = $this->scheduler->previewUpcomingRuns(
            scheduleType: $validated['schedule_type'],
            schedule: $validated['schedule'],
            timezone: $validated['timezone'] ?? 'Europe/Warsaw',
            count: (int) ($validated['count'] ?? 5)
        );

        return response()->json([
            'success' => true,
            'runs' => $runs,
        ]);
    }
}
