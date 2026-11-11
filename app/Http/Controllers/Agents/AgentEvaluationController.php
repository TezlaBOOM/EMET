<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agents;

use App\Contracts\Agents\AgentEvaluationInterface;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentEvalRun;
use App\Models\AgentEvalSuite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentEvaluationController extends Controller
{
    public function __construct(
        protected AgentEvaluationInterface $evaluationService
    ) {}

    public function index(): View
    {
        $suites = AgentEvalSuite::with(['agent', 'runs' => fn ($q) => $q->latest()->limit(5)])
            ->latest()
            ->get();

        $agents = Agent::where('is_active', true)->orderBy('name')->get();
        $recentRuns = AgentEvalRun::with(['suite', 'agent'])->latest()->limit(15)->get();

        return view('module-agents::evals', [
            'suites' => $suites,
            'agents' => $agents,
            'recentRuns' => $recentRuns,
        ]);
    }

    public function storeSuite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'judge_model' => ['nullable', 'string', 'max:100'],
            'pass_threshold' => ['required', 'numeric', 'min:0.1', 'max:1.0'],
            'cases_json' => ['required', 'string'],
        ]);

        $cases = json_decode($validated['cases_json'], true);
        if (! is_array($cases) || empty($cases)) {
            return redirect()
                ->route('agents.evals.index')
                ->with('error', __('agents.evals.invalid_cases_json'));
        }

        AgentEvalSuite::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'agent_id' => $validated['agent_id'] ?: null,
            'pass_threshold' => (float) $validated['pass_threshold'],
            'test_cases' => $cases,
        ]);

        return redirect()
            ->route('agents.evals.index')
            ->with('status', __('agents.evals.suite_created'));
    }

    public function runSuite(Request $request, AgentEvalSuite $suite): RedirectResponse
    {
        $validated = $request->validate([
            'model_a' => ['nullable', 'string', 'max:100'],
            'model_b' => ['nullable', 'string', 'max:100'],
        ]);

        $modelA = ! empty($validated['model_a'])
            ? (string) $validated['model_a']
            : ($suite->agent?->primary_model ?: 'gpt-4o-mini');

        $run = $this->evaluationService->runSuite(
            suite: $suite,
            modelA: $modelA,
            modelB: $validated['model_b'] ?: null
        );

        return redirect()
            ->route('agents.evals.index')
            ->with('status', __('agents.evals.run_completed', [
                'status' => strtoupper($run->status),
                'score' => number_format($run->score_a * 100, 1).'%',
            ]));
    }
}
