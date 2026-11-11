<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Contracts\Agents\AgentCooperationInterface;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentCooperation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgentCooperationController extends Controller
{
    public function __construct(
        protected AgentCooperationInterface $cooperationService
    ) {}

    public function index(Request $request): View
    {
        $agents = Agent::with('skills')->orderBy('name')->get();
        $cooperations = AgentCooperation::with('coordinator')->latest()->get();

        $selectedId = (int) $request->query('cooperation_id', 0);
        $activeCooperation = $selectedId > 0
            ? $cooperations->firstWhere('id', $selectedId)
            : $cooperations->first();

        $overheatTripsCount = $cooperations->sum(fn (AgentCooperation $c) => count($c->overheat_events ?? []));
        $avgTemperature = $cooperations->isNotEmpty()
            ? round((float) $cooperations->avg('current_temperature'), 1)
            : 22.0;

        return view('agents::cooperation', [
            'agents' => $agents,
            'cooperations' => $cooperations,
            'activeCooperation' => $activeCooperation,
            'overheatTripsCount' => $overheatTripsCount,
            'avgTemperature' => $avgTemperature,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1000'],
            'topology' => ['required', 'string', 'in:round_table,pipeline,supervisor,debate_consensus'],
            'coordinator_agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'participant_agent_ids' => ['required', 'array', 'min:2'],
            'participant_agent_ids.*' => ['integer', 'exists:agents,id'],
            'overheat_guard_enabled' => ['nullable', 'boolean'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'max_tokens_budget' => ['required', 'integer', 'min:200', 'max:500000'],
            'max_temperature_score' => ['required', 'numeric', 'min:45', 'max:100'],
            'cooldown_seconds' => ['nullable', 'integer', 'min:5', 'max:3600'],
            'repetition_threshold' => ['required', 'numeric', 'min:0.30', 'max:0.99'],
            'auto_synthesize_on_overheat' => ['nullable', 'boolean'],
        ]);

        $participantIds = array_values(array_unique(array_map('intval', $validated['participant_agent_ids'])));
        $coordinatorId = ! empty($validated['coordinator_agent_id'])
            ? (int) $validated['coordinator_agent_id']
            : ($participantIds[0] ?? null);

        $cooperation = AgentCooperation::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::lower(Str::random(5)),
            'description' => $validated['description'] ?? null,
            'topology' => $validated['topology'],
            'coordinator_agent_id' => $coordinatorId,
            'participant_agent_ids' => $participantIds,
            'overheat_guard_enabled' => $request->boolean('overheat_guard_enabled', true),
            'max_rounds' => (int) $validated['max_rounds'],
            'max_tokens_budget' => (int) $validated['max_tokens_budget'],
            'max_temperature_score' => (float) $validated['max_temperature_score'],
            'cooldown_seconds' => (int) ($validated['cooldown_seconds'] ?? 30),
            'repetition_threshold' => (float) $validated['repetition_threshold'],
            'auto_synthesize_on_overheat' => $request->boolean('auto_synthesize_on_overheat', true),
            'current_temperature' => 22.0,
            'thermal_status' => AgentCooperation::THERMAL_NOMINAL,
            'status' => 'idle',
        ]);

        return redirect()
            ->route('agents.cooperation.index', ['cooperation_id' => $cooperation->id])
            ->with('status', __('agents.cooperation.created_success'));
    }

    public function runTask(Request $request, AgentCooperation $cooperation): RedirectResponse
    {
        $validated = $request->validate([
            'task_prompt' => ['required', 'string', 'min:3', 'max:5000'],
            'rounds_override' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $updated = $this->cooperationService->runCooperationTask(
            $cooperation,
            $validated['task_prompt'],
            isset($validated['rounds_override']) ? (int) $validated['rounds_override'] : null
        );

        if ($updated->status === 'overheated') {
            return redirect()
                ->route('agents.cooperation.index', ['cooperation_id' => $updated->id])
                ->with('error', __('agents.cooperation.overheat_tripped_notice', [
                    'temp' => number_format($updated->current_temperature, 1),
                ]));
        }

        return redirect()
            ->route('agents.cooperation.index', ['cooperation_id' => $updated->id])
            ->with('status', __('agents.cooperation.task_completed_success'));
    }

    public function updateGuard(Request $request, AgentCooperation $cooperation): RedirectResponse
    {
        $validated = $request->validate([
            'overheat_guard_enabled' => ['nullable', 'boolean'],
            'max_rounds' => ['required', 'integer', 'min:1', 'max:20'],
            'max_tokens_budget' => ['required', 'integer', 'min:200', 'max:500000'],
            'max_temperature_score' => ['required', 'numeric', 'min:45', 'max:100'],
            'repetition_threshold' => ['required', 'numeric', 'min:0.30', 'max:0.99'],
            'auto_synthesize_on_overheat' => ['nullable', 'boolean'],
        ]);

        $cooperation->update([
            'overheat_guard_enabled' => $request->boolean('overheat_guard_enabled'),
            'max_rounds' => (int) $validated['max_rounds'],
            'max_tokens_budget' => (int) $validated['max_tokens_budget'],
            'max_temperature_score' => (float) $validated['max_temperature_score'],
            'repetition_threshold' => (float) $validated['repetition_threshold'],
            'auto_synthesize_on_overheat' => $request->boolean('auto_synthesize_on_overheat'),
        ]);

        return redirect()
            ->route('agents.cooperation.index', ['cooperation_id' => $cooperation->id])
            ->with('status', __('agents.cooperation.guard_updated_success'));
    }

    public function cooldown(Request $request, AgentCooperation $cooperation): RedirectResponse
    {
        $customNote = $request->input('custom_context_note');
        $this->cooperationService->cooldown(
            $cooperation,
            is_string($customNote) && trim($customNote) !== '' ? trim($customNote) : null
        );

        return redirect()
            ->route('agents.cooperation.index', ['cooperation_id' => $cooperation->id])
            ->with('status', __('agents.cooperation.cooled_down_success'));
    }

    public function destroy(AgentCooperation $cooperation): RedirectResponse
    {
        $cooperation->delete();

        return redirect()
            ->route('agents.cooperation.index')
            ->with('status', __('agents.cooperation.deleted_success'));
    }
}
