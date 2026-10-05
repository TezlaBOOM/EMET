<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\AuditLog;
use App\Models\LlmAccountPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        $agents = Agent::with(['pool', 'skills'])->latest()->paginate(12);

        return view('module-agents::index', compact('agents'));
    }

    public function active(): View
    {
        $agents = Agent::with(['pool', 'skills'])->where('is_active', true)->latest()->paginate(12);

        return view('module-agents::index', compact('agents'));
    }

    public function create(): View
    {
        $pools = LlmAccountPool::where('is_active', true)->get();

        return view('module-agents::create', compact('pools'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'unique:agents,slug'],
            'description' => ['nullable', 'string'],
            'runtime_type' => ['required', 'string', 'in:native,hermes,openclaw,claude_code,codex'],
            'pool_id' => ['nullable', 'exists:llm_account_pools,id'],
            'primary_model' => ['required', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');

        $agent = Agent::create($validated);

        // Dodanie domyślnych skilli (np. web_search, file_editor)
        $defaultSkills = [
            ['name' => 'Web Search', 'driver' => 'web_search', 'is_enabled' => true],
            ['name' => 'File Editor', 'driver' => 'file_editor', 'is_enabled' => true],
            ['name' => 'Memory Search', 'driver' => 'memory_search', 'is_enabled' => true],
        ];

        foreach ($defaultSkills as $skill) {
            $agent->skills()->create($skill);
        }

        AuditLog::record('agent.created', 'Agent', (string) $agent->id, [
            'name' => $agent->name,
            'runtime_type' => $agent->runtime_type,
            'primary_model' => $agent->primary_model,
        ]);

        return redirect()->route('agents.index')->with('success', __('agents.created_success'));
    }

    public function edit(Agent $agent): View
    {
        $pools = LlmAccountPool::where('is_active', true)->get();
        $agent->load('skills');

        return view('module-agents::edit', compact('agent', 'pools'));
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'runtime_type' => ['required', 'string', 'in:native,hermes,openclaw,claude_code,codex'],
            'pool_id' => ['nullable', 'exists:llm_account_pools,id'],
            'primary_model' => ['required', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->has('is_active');

        $agent->update($validated);

        AuditLog::record('agent.updated', 'Agent', (string) $agent->id, [
            'name' => $agent->name,
            'primary_model' => $agent->primary_model,
        ]);

        return back()->with('success', __('agents.updated_success'));
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $id = (string) $agent->id;
        $name = $agent->name;
        $agent->delete();

        AuditLog::record('agent.deleted', 'Agent', $id, ['name' => $name]);

        return redirect()->route('agents.index')->with('success', __('agents.deleted_success'));
    }

    public function toggleSkill(Agent $agent, AgentSkill $skill): RedirectResponse
    {
        if ($skill->agent_id !== $agent->id) {
            abort(403);
        }

        $skill->update(['is_enabled' => ! $skill->is_enabled]);

        return back()->with('success', "Zmieniono status umiejętności {$skill->name}.");
    }

    public function templates(): View
    {
        $templates = [
            [
                'name' => 'Badacz / Analityk Wiedzy',
                'description' => 'Agent zoptymalizowany pod kątem głębokiej syntezy informacji, przeszukiwania źródeł i generowania ustrukturyzowanych raportów.',
                'runtime_type' => 'native',
                'primary_model' => 'claude-3-5-sonnet',
                'temperature' => 0.3,
                'system_prompt' => "Jesteś analitykiem wiedzy. Odpowiadaj rzeczowo, podając źródła i logiczne uzasadnienie.",
            ],
            [
                'name' => 'Inżynier Oprogramowania (Coder)',
                'description' => 'Agent wyspecjalizowany w pisaniu, refaktoryzacji i analizie architektury kodu.',
                'runtime_type' => 'native',
                'primary_model' => 'gpt-4o',
                'temperature' => 0.2,
                'system_prompt' => "Jesteś ekspertem inżynierii oprogramowania. Twórz czysty, bezpieczny kod zgodny z zasadami SOLID i DRY.",
            ],
            [
                'name' => 'Hermes Autonomiczny Asystent',
                'description' => 'Instancja agenta Hermes dedykowana do długotrwałych zadań powłoki i automatyzacji systemowej.',
                'runtime_type' => 'hermes',
                'primary_model' => 'gemini-2.5-flash',
                'temperature' => 0.7,
                'system_prompt' => "Jesteś autonomicznym agentem wykonawczym Hermes.",
            ],
        ];

        return view('module-agents::templates', compact('templates'));
    }
}
