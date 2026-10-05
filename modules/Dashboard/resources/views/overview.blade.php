<x-layouts.app active-module="dashboard" active-subcategory="overview" :title="__('dashboard.title')">
    <div x-data="{
        period: '{{ $period }}',
        activeAgents: {{ (int) ($activeAgents['active_count'] ?? 0) }},
        totalTokens: {{ (int) ($tokenMetrics['total_tokens'] ?? 0) }},
        avgTtft: {{ (int) ($performance['avg_ttft_ms'] ?? 0) }},
        totalCost: '{{ number_format($costs['total_cost_usd'] ?? 0, 4) }}',
        init() {
            setInterval(() => {
                fetch('{{ route('dashboard.metrics.api') }}?period=' + this.period)
                    .then(r => r.json())
                    .then(data => {
                        this.activeAgents = data.active_agents.active_count;
                        this.totalTokens = data.token_metrics.total_tokens;
                        this.avgTtft = data.performance.avg_ttft_ms;
                        this.totalCost = Number(data.costs.total_cost_usd).toFixed(4);
                    })
                    .catch(() => {});
            }, 5000);
        }
    }" class="space-y-6">

        <!-- Nagłówek i przełącznik okresu -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('dashboard.title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('dashboard.sub_overview') }} – Projekt-Emet Telemetry & Orchestration Platform
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-600/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>{{ __('dashboard.live_refresh') }}</span>
                </div>

                <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-medium">
                    <a href="?period=1h" class="px-3 py-1 rounded-lg transition {{ $period === '1h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_1h') }}</a>
                    <a href="?period=24h" class="px-3 py-1 rounded-lg transition {{ $period === '24h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_24h') }}</a>
                    <a href="?period=7d" class="px-3 py-1 rounded-lg transition {{ $period === '7d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_7d') }}</a>
                    <a href="?period=30d" class="px-3 py-1 rounded-lg transition {{ $period === '30d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_30d') }}</a>
                </div>
            </div>
        </div>

        <!-- Kafelki KPI -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between text-slate-400 dark:text-slate-500">
                    <span class="text-xs font-semibold uppercase tracking-wider">{{ __('dashboard.active_agents') }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">{{ $activeAgents['total_agents'] ?? 0 }} total</span>
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2" x-text="activeAgents">
                    {{ $activeAgents['active_count'] ?? 0 }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    {{ __('dashboard.active_agents_subtitle') }}
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('dashboard.token_usage_24h') }}
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2" x-text="totalTokens.toLocaleString()">
                    {{ number_format($tokenMetrics['total_tokens'] ?? 0) }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-2 flex justify-between">
                    <span>In: {{ number_format($tokenMetrics['prompt_tokens'] ?? 0) }}</span>
                    <span>Out: {{ number_format($tokenMetrics['completion_tokens'] ?? 0) }}</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('dashboard.avg_latency_ttft') }}
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    <span x-text="avgTtft">{{ $performance['avg_ttft_ms'] ?? 0 }}</span> ms
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-2 flex justify-between">
                    <span>p50: {{ $performance['p50_duration_ms'] ?? 0 }} ms</span>
                    <span>p95: {{ $performance['p95_duration_ms'] ?? 0 }} ms</span>
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    {{ __('dashboard.estimated_cost') }}
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    $<span x-text="totalCost">{{ number_format($costs['total_cost_usd'] ?? 0, 4) }}</span>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    {{ __('dashboard.cost_summary') }}
                </div>
            </div>
        </div>

        <!-- Sekcja 2 kolumn: Aktywni agenci i Zdrowie integracji -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Aktywni agenci (2 kolumny) -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ __('dashboard.active_agents') }}
                    </h2>
                    <a href="{{ route('agents.index') }}" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                        {{ __('agents.sub_all') }} &rarr;
                    </a>
                </div>

                @if(empty($activeAgents['agents']))
                    <div class="text-center py-8 text-slate-400 text-sm">
                        {{ __('dashboard.no_active_agents') }}
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($activeAgents['agents'] as $agent)
                            <div class="flex items-center justify-between p-3.5 rounded-xl border border-slate-100 dark:border-slate-800/80 hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full {{ $agent['status'] === 'working' ? 'bg-emerald-500 animate-pulse' : ($agent['status'] === 'error' ? 'bg-rose-500' : 'bg-slate-300 dark:bg-slate-600') }}"></div>
                                    <div>
                                        <div class="font-medium text-sm text-slate-900 dark:text-white">{{ $agent['name'] }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $agent['current_task'] }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block px-2 py-0.5 rounded text-2xs font-semibold uppercase {{ $agent['status'] === 'working' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ __('dashboard.agent_status_' . $agent['status']) }}
                                    </span>
                                    <div class="text-2xs text-slate-400 mt-1">{{ number_format($agent['total_tokens']) }} tok</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Zdrowie integracji (1 kolumna) -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">
                        {{ __('dashboard.integrations_health') }}
                    </h2>
                    <a href="{{ route('integrations.index') }}" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                        Zarządzaj &rarr;
                    </a>
                </div>

                @if(empty($integrations['instances']))
                    <div class="text-center py-8 text-slate-400 text-sm">
                        {{ __('dashboard.no_instances') }}
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($integrations['instances'] as $inst)
                            <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $inst['name'] }}</div>
                                    <div class="text-2xs text-slate-400 uppercase mt-0.5">{{ $inst['type'] }} &bull; Port {{ $inst['port'] ?? '-' }}</div>
                                </div>
                                <div>
                                    @if($inst['status'] === 'running')
                                        <span class="px-2 py-0.5 rounded text-2xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                            {{ __('dashboard.status_running') }}
                                        </span>
                                    @elseif($inst['status'] === 'degraded')
                                        <span class="px-2 py-0.5 rounded text-2xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                            {{ __('dashboard.status_degraded') }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-2xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                                            {{ __('dashboard.status_error') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Rozbicie na modele -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                {{ __('dashboard.model_breakdown') }}
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                            <th class="pb-3">{{ __('dashboard.table_model') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_calls') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_prompt_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_completion_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_total_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($tokenMetrics['by_model'] as $row)
                            <tr>
                                <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $row['model'] }}</td>
                                <td class="py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($row['calls']) }}</td>
                                <td class="py-3 text-right text-slate-500">{{ number_format($row['prompt_tokens']) }}</td>
                                <td class="py-3 text-right text-slate-500">{{ number_format($row['completion_tokens']) }}</td>
                                <td class="py-3 text-right font-medium text-slate-900 dark:text-white">{{ number_format($row['total_tokens']) }}</td>
                                <td class="py-3 text-right font-semibold text-indigo-600 dark:text-indigo-400">${{ number_format($row['cost_usd'], 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 text-sm">Brak wywołań modeli w wybranym okresie.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts.app>
