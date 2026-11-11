<x-layouts.app active-module="agents" active-subcategory="evals" :title="__('agents.evals.title')">
    <div class="space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('agents.evals.title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('agents.evals.subtitle') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formularz tworzenia zestawu testowego -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                    {{ __('agents.evals.create_suite') }}
                </h2>

                <form method="POST" action="{{ route('agents.evals.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                            {{ __('agents.evals.suite_name') }}
                        </label>
                        <input type="text" name="name" required placeholder="np. Regresja RAG i Bezpieczeństwa v1"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                            {{ __('agents.name') }}
                        </label>
                        <select name="agent_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="">— Domyślny / Dowolny —</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->primary_model }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                            {{ __('agents.evals.pass_threshold') }}
                        </label>
                        <input type="number" step="0.05" min="0.1" max="1.0" name="pass_threshold" value="0.75" required
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                            {{ __('agents.evals.cases_json') }}
                        </label>
                        <textarea name="cases_json" rows="6" required
                                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">[
  {
    "prompt": "Wymień 3 warstwy pamięci w architekturze AgentHub.",
    "expected_keywords": ["krótkotrwała", "epizodyczna", "semantyczna"],
    "reference_answer": "AgentHub posiada pamięć krótkotrwałą (STM), epizodyczną oraz semantyczną (RAG)."
  }
]</textarea>
                    </div>

                    <button type="submit"
                            class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-all">
                        {{ __('agents.evals.save_suite') }}
                    </button>
                </form>
            </div>

            <!-- Lista zestawów i uruchamianie Benchmarku A/B -->
            <div class="lg:col-span-2 space-y-6">
                @forelse($suites as $suite)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ $suite->name }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Przypadków testowych: <strong>{{ count($suite->test_cases ?? []) }}</strong> |
                                    Próg zaliczenia: <strong>{{ number_format($suite->pass_threshold * 100, 0) }}%</strong>
                                </p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('agents.evals.run', $suite) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                            @csrf
                            <input type="text" name="model_a" placeholder="Model A (np. gpt-4o-mini)" value="{{ $suite->agent?->primary_model ?? 'gpt-4o-mini' }}"
                                   class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                            <input type="text" name="model_b" placeholder="Model B opcjonalnie (np. claude-3-5-sonnet)"
                                   class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                            <button type="submit"
                                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition-all">
                                ▶ {{ __('agents.evals.run_ab') }}
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                        Brak zdefiniowanych zestawów ewaluacyjnych. Utwórz pierwszy zestaw Golden Dataset po lewej stronie.
                    </div>
                @endforelse

                <!-- Tabela ostatnich uruchomień A/B -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                            {{ __('agents.evals.recent_runs') }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 uppercase text-slate-500 border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th class="px-4 py-3">Zestaw</th>
                                    <th class="px-4 py-3">Model A (Wynik / Czas)</th>
                                    <th class="px-4 py-3">Model B (Wynik / Czas)</th>
                                    <th class="px-4 py-3">Zwycięzca A/B</th>
                                    <th class="px-4 py-3">Status CI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($recentRuns as $run)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                            {{ $run->suite?->name ?? '#' . $run->suite_id }}
                                        </td>
                                        <td class="px-4 py-3 font-mono">
                                            {{ $run->model_a }}: <strong>{{ number_format($run->score_a * 100, 1) }}%</strong> ({{ $run->avg_latency_ms_a }} ms)
                                        </td>
                                        <td class="px-4 py-3 font-mono">
                                            @if($run->model_b)
                                                {{ $run->model_b }}: <strong>{{ number_format((float) $run->score_b * 100, 1) }}%</strong> ({{ $run->avg_latency_ms_b }} ms)
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-mono font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ $run->winner_model }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $run->status === 'passed' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' }}">
                                                {{ strtoupper($run->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                                            Brak uruchomionych testów ewaluacyjnych.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
