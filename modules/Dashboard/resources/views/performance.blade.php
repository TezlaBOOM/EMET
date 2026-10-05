<x-layouts.app active-module="dashboard" active-subcategory="performance" :title="__('dashboard.sub_performance')">
    <div class="space-y-6">
        <!-- Nagłówek i przełącznik okresu -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('dashboard.sub_performance') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('dashboard.performance_overview') }}
                </p>
            </div>
            <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-medium">
                <a href="?period=1h" class="px-3 py-1 rounded-lg transition {{ $period === '1h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_1h') }}</a>
                <a href="?period=24h" class="px-3 py-1 rounded-lg transition {{ $period === '24h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_24h') }}</a>
                <a href="?period=7d" class="px-3 py-1 rounded-lg transition {{ $period === '7d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_7d') }}</a>
                <a href="?period=30d" class="px-3 py-1 rounded-lg transition {{ $period === '30d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_30d') }}</a>
            </div>
        </div>

        <!-- Karty KPI wydajności -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('dashboard.table_ttft') }}</div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">{{ $performance['avg_ttft_ms'] ?? 0 }} ms</div>
                <div class="text-xs text-slate-500 mt-2">Czas do 1. wyemitowanego tokena</div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">Mediana (p50)</div>
                <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-2">{{ $performance['p50_duration_ms'] ?? 0 }} ms</div>
                <div class="text-xs text-slate-500 mt-2">50% zapytań kończy się szybciej</div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">95 Percentyl (p95)</div>
                <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-2">{{ $performance['p95_duration_ms'] ?? 0 }} ms</div>
                <div class="text-xs text-slate-500 mt-2">Górny margines opóźnień</div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('dashboard.table_success_rate') }}</div>
                <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2">{{ $performance['success_rate_percent'] ?? 100 }}%</div>
                <div class="text-xs text-slate-500 mt-2">Błędy: {{ $performance['error_count'] ?? 0 }}</div>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-2">Monitor jakości bramy LLM Gateway</h2>
            <p class="text-sm text-slate-500 mb-6">
                Metryki są agregowane w czasie rzeczywistym i synchronizowane z tabelami rollupów.
                W przypadku wykrycia błędów 429 (Rate Limit), brama automatycznie włącza dynamiczny cooldown i failover.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60">
                    <div class="text-xs text-slate-400 uppercase font-semibold">Średni całkowity czas odpowiedzi</div>
                    <div class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">{{ $performance['avg_duration_ms'] ?? 0 }} ms</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60">
                    <div class="text-xs text-slate-400 uppercase font-semibold">Całkowita liczba przetworzonych wywołań</div>
                    <div class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">{{ number_format($performance['total_calls'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
