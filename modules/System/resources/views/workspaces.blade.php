<x-layouts.app active-module="system" active-subcategory="workspaces" :title="__('system.workspaces.title')">
    <div class="space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('system.workspaces.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('system.workspaces.subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                    {{ __('system.workspaces.create') }}
                </h2>

                <form method="POST" action="{{ route('system.workspaces.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nazwa przestrzeni roboczej</label>
                        <input type="text" name="name" required placeholder="np. Dział R&D / Analityka"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Budżet dzienny ($)</label>
                            <input type="number" step="0.5" name="daily_budget_usd" value="15.00" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Budżet miesięczny ($)</label>
                            <input type="number" step="1" name="monthly_budget_usd" value="300.00" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Limit tokenów / dzień</label>
                            <input type="number" name="daily_token_limit" value="500000" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Próg alertu (%)</label>
                            <input type="number" name="alert_threshold_pct" value="80" required
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Lokalny model awaryjny (Fallback)</label>
                        <input type="text" name="fallback_model" value="ollama/llama3.2" required
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm">
                        {{ __('system.workspaces.create') }}
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-4">
                @forelse($workspaces as $ws)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $ws->name }}</h3>
                            <p class="text-xs text-slate-500 font-mono mt-0.5">
                                Dzisiaj: ${{ number_format($ws->spent_today_usd, 4) }} / ${{ number_format($ws->daily_budget_usd, 2) }} |
                                Miesiąc: ${{ number_format($ws->spent_month_usd, 4) }} / ${{ number_format($ws->monthly_budget_usd, 2) }} |
                                Tokeny: {{ number_format($ws->tokens_used_today) }} / {{ number_format($ws->daily_token_limit) }}
                            </p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-mono bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                            Fallback: {{ $ws->fallback_model }}
                        </span>
                    </div>
                @empty
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                        Brak zdefiniowanych przestrzeni roboczych FinOps.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>

