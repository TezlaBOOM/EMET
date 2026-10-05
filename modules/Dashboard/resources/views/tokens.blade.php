<x-layouts.app active-module="dashboard" active-subcategory="tokens" :title="__('dashboard.sub_tokens')">
    <div class="space-y-6">
        <!-- Nagłówek i przełącznik okresu -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('dashboard.sub_tokens') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Analiza wolumenu prompt vs completion per model i dostawca
                </p>
            </div>
            <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-medium">
                <a href="?period=1h" class="px-3 py-1 rounded-lg transition {{ $period === '1h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_1h') }}</a>
                <a href="?period=24h" class="px-3 py-1 rounded-lg transition {{ $period === '24h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_24h') }}</a>
                <a href="?period=7d" class="px-3 py-1 rounded-lg transition {{ $period === '7d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_7d') }}</a>
                <a href="?period=30d" class="px-3 py-1 rounded-lg transition {{ $period === '30d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_30d') }}</a>
            </div>
        </div>

        <!-- Karty podsumowania -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('dashboard.table_total_tokens') }}</div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">{{ number_format($tokenMetrics['total_tokens'] ?? 0) }}</div>
                <div class="text-xs text-slate-500 mt-2">{{ number_format($tokenMetrics['total_calls'] ?? 0) }} zarejestrowanych wywołań</div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('dashboard.table_prompt_tokens') }}</div>
                <div class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-2">{{ number_format($tokenMetrics['prompt_tokens'] ?? 0) }}</div>
                <div class="text-xs text-slate-500 mt-2">
                    @php
                        $inPct = ($tokenMetrics['total_tokens'] ?? 0) > 0 ? round((($tokenMetrics['prompt_tokens'] ?? 0) / $tokenMetrics['total_tokens']) * 100, 1) : 0;
                    @endphp
                    {{ $inPct }}% całkowitego wolumenu
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ __('dashboard.table_completion_tokens') }}</div>
                <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2">{{ number_format($tokenMetrics['completion_tokens'] ?? 0) }}</div>
                <div class="text-xs text-slate-500 mt-2">
                    @php
                        $outPct = ($tokenMetrics['total_tokens'] ?? 0) > 0 ? round((($tokenMetrics['completion_tokens'] ?? 0) / $tokenMetrics['total_tokens']) * 100, 1) : 0;
                    @endphp
                    {{ $outPct }}% całkowitego wolumenu
                </div>
            </div>
        </div>

        <!-- Podział wg modeli -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('dashboard.model_breakdown') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                            <th class="pb-3">{{ __('dashboard.table_model') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_calls') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_prompt_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_completion_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_total_tokens') }}</th>
                            <th class="pb-3 text-right">Udział</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($tokenMetrics['by_model'] as $m)
                            @php
                                $mShare = ($tokenMetrics['total_tokens'] ?? 0) > 0 ? round(($m['total_tokens'] / $tokenMetrics['total_tokens']) * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $m['model'] }}</td>
                                <td class="py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($m['calls']) }}</td>
                                <td class="py-3 text-right text-slate-500">{{ number_format($m['prompt_tokens']) }}</td>
                                <td class="py-3 text-right text-slate-500">{{ number_format($m['completion_tokens']) }}</td>
                                <td class="py-3 text-right font-medium text-slate-900 dark:text-white">{{ number_format($m['total_tokens']) }}</td>
                                <td class="py-3 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <div class="w-16 bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $mShare }}%"></div>
                                        </div>
                                        <span class="text-xs text-slate-400">{{ $mShare }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-400 text-sm">Brak zarejestrowanych danych o tokenach.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Podział wg dostawców -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('dashboard.provider_breakdown') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                            <th class="pb-3">{{ __('dashboard.table_provider') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_calls') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_total_tokens') }}</th>
                            <th class="pb-3 text-right">{{ __('dashboard.table_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse($tokenMetrics['by_provider'] as $p)
                            <tr>
                                <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $p['provider_name'] }}</td>
                                <td class="py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($p['calls']) }}</td>
                                <td class="py-3 text-right font-medium text-slate-900 dark:text-white">{{ number_format($p['total_tokens']) }}</td>
                                <td class="py-3 text-right font-semibold text-indigo-600 dark:text-indigo-400">${{ number_format($p['cost_usd'], 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-400 text-sm">Brak zarejestrowanych danych o dostawcach.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</x-layouts.app>
