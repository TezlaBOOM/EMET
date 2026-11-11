<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\AuditLog;
use App\Models\LlmAccountPool;
use App\Models\Skill;
use App\Rules\ValidModelForAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(): View
    {
        $agents = Agent::with(['pool', 'skills'])->latest()->paginate(12);
        $availableSkills = Skill::where('status', Skill::STATUS_ACTIVE)->orderBy('name')->get();

        return view('module-agents::index', compact('agents', 'availableSkills'));
    }

    public function active(): View
    {
        $agents = Agent::with(['pool', 'skills'])->where('is_active', true)->latest()->paginate(12);
        $availableSkills = Skill::where('status', Skill::STATUS_ACTIVE)->orderBy('name')->get();

        return view('module-agents::index', compact('agents', 'availableSkills'));
    }

    public function create(): View
    {
        $pools = LlmAccountPool::where('is_active', true)->get();
        $availableSkills = Skill::where('status', Skill::STATUS_ACTIVE)->orderBy('name')->get();

        return view('module-agents::create', compact('pools', 'availableSkills'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'unique:agents,slug'],
            'description' => ['nullable', 'string'],
            'runtime_type' => ['required', 'string', 'in:native,hermes,openclaw,claude_code,codex'],
            'pool_id' => ['nullable', 'exists:llm_account_pools,id'],
            'primary_model' => [
                'required',
                'string',
                'max:100',
                new ValidModelForAgent($request->filled('pool_id') ? (int) $request->input('pool_id') : null),
            ],
            'system_prompt' => ['nullable', 'string'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'is_active' => ['nullable', 'boolean'],
            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', 'exists:skills,id'],
        ]);

        $skillIds = $validated['skill_ids'] ?? [];
        unset($validated['skill_ids']);

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

        if (! empty($skillIds)) {
            $catalogSkills = Skill::whereIn('id', $skillIds)->get();
            foreach ($catalogSkills as $catalogSkill) {
                $agent->skills()->updateOrCreate(
                    ['driver' => $catalogSkill->slug],
                    [
                        'skill_id' => $catalogSkill->id,
                        'skill_version_id' => $catalogSkill->current_version_id,
                        'name' => $catalogSkill->name,
                        'is_enabled' => true,
                    ]
                );
            }
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
        $availableSkills = Skill::where('status', Skill::STATUS_ACTIVE)->orderBy('name')->get();
        $agent->load('skills');

        return view('module-agents::edit', compact('agent', 'pools', 'availableSkills'));
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'runtime_type' => ['required', 'string', 'in:native,hermes,openclaw,claude_code,codex'],
            'pool_id' => ['nullable', 'exists:llm_account_pools,id'],
            'primary_model' => [
                'required',
                'string',
                'max:100',
                new ValidModelForAgent($request->filled('pool_id') ? (int) $request->input('pool_id') : null),
            ],
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

    public function attachSkill(Request $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validate([
            'skill_id' => ['nullable', 'integer', 'exists:skills,id'],
            'custom_name' => ['nullable', 'string', 'max:100'],
            'custom_driver' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($validated['skill_id'])) {
            $catalogSkill = Skill::findOrFail((int) $validated['skill_id']);

            $agentSkill = $agent->skills()->where('skill_id', $catalogSkill->id)
                ->orWhere(function ($q) use ($agent, $catalogSkill) {
                    $q->where('agent_id', $agent->id)->where('driver', $catalogSkill->slug);
                })
                ->first();

            if ($agentSkill) {
                $agentSkill->update([
                    'skill_id' => $catalogSkill->id,
                    'skill_version_id' => $catalogSkill->current_version_id,
                    'name' => $catalogSkill->name,
                    'is_enabled' => true,
                ]);
            } else {
                $agentSkill = $agent->skills()->create([
                    'skill_id' => $catalogSkill->id,
                    'skill_version_id' => $catalogSkill->current_version_id,
                    'name' => $catalogSkill->name,
                    'driver' => $catalogSkill->slug,
                    'is_enabled' => true,
                ]);
            }

            AuditLog::record('agent.skill.attached', 'Agent', (string) $agent->id, [
                'skill_id' => $catalogSkill->id,
                'skill_name' => $catalogSkill->name,
            ]);

            return back()->with('success', __('agents.skill_attached_success', ['skill' => $catalogSkill->name, 'agent' => $agent->name]));
        }

        if (! empty($validated['custom_name'])) {
            $driver = ! empty($validated['custom_driver'])
                ? Str::slug($validated['custom_driver'], '_')
                : Str::slug($validated['custom_name'], '_');

            $agentSkill = $agent->skills()->updateOrCreate(
                ['driver' => $driver],
                [
                    'name' => $validated['custom_name'],
                    'is_enabled' => true,
                ]
            );

            AuditLog::record('agent.skill.attached', 'Agent', (string) $agent->id, [
                'skill_name' => $agentSkill->name,
                'driver' => $driver,
            ]);

            return back()->with('success', __('agents.skill_attached_success', ['skill' => $agentSkill->name, 'agent' => $agent->name]));
        }

        return back();
    }

    public function toggleSkill(Agent $agent, AgentSkill $skill): RedirectResponse
    {
        if ($skill->agent_id !== $agent->id) {
            abort(403);
        }

        $skill->update(['is_enabled' => ! $skill->is_enabled]);

        return back()->with('success', "Zmieniono status umiejętności {$skill->name}.");
    }

    public function detachSkill(Agent $agent, AgentSkill $skill): RedirectResponse
    {
        if ($skill->agent_id !== $agent->id) {
            abort(403);
        }

        $skillName = $skill->name;
        $skill->delete();

        AuditLog::record('agent.skill.detached', 'Agent', (string) $agent->id, [
            'skill_name' => $skillName,
        ]);

        return back()->with('success', __('agents.skill_detached_success', ['skill' => $skillName, 'agent' => $agent->name]));
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
                'system_prompt' => 'Jesteś analitykiem wiedzy. Odpowiadaj rzeczowo, podając źródła i logiczne uzasadnienie.',
            ],
            [
                'name' => 'Inżynier Oprogramowania (Coder)',
                'description' => 'Agent wyspecjalizowany w pisaniu, refaktoryzacji i analizie architektury kodu.',
                'runtime_type' => 'native',
                'primary_model' => 'gpt-4o',
                'temperature' => 0.2,
                'system_prompt' => 'Jesteś ekspertem inżynierii oprogramowania. Twórz czysty, bezpieczny kod zgodny z zasadami SOLID i DRY.',
            ],
            [
                'name' => 'Hermes Autonomiczny Asystent',
                'description' => 'Instancja agenta Hermes dedykowana do długotrwałych zadań powłoki i automatyzacji systemowej.',
                'runtime_type' => 'hermes',
                'primary_model' => 'gemini-1.5-flash',
                'temperature' => 0.7,
                'system_prompt' => 'Jesteś autonomicznym agentem wykonawczym Hermes.',
            ],
        ];

        return view('module-agents::templates', compact('templates'));
    }
}
