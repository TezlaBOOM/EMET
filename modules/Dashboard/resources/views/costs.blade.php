<x-layouts.app active-module="dashboard" active-subcategory="costs" :title="__('dashboard.sub_costs')">
    <div class="space-y-6">
        <!-- Nagłówek i przełącznik okresu -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('dashboard.sub_costs') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('dashboard.costs_overview') }}
                </p>
            </div>
            <div class="inline-flex bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-medium">
                <a href="?period=1h" class="px-3 py-1 rounded-lg transition {{ $period === '1h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_1h') }}</a>
                <a href="?period=24h" class="px-3 py-1 rounded-lg transition {{ $period === '24h' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_24h') }}</a>
                <a href="?period=7d" class="px-3 py-1 rounded-lg transition {{ $period === '7d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_7d') }}</a>
                <a href="?period=30d" class="px-3 py-1 rounded-lg transition {{ $period === '30d' ? 'bg-white dark:bg-slate-900 text-indigo-600 shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900' }}">{{ __('dashboard.filter_30d') }}</a>
            </div>
        </div>

        <!-- Główna karta kosztów -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Łączny szacowany koszt wywołań AI
            </div>
            <div class="text-4xl font-extrabold text-indigo-600 dark:text-indigo-400 mt-2">
                ${{ number_format($costs['total_cost_usd'] ?? 0, 4) }} USD
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                {{ __('dashboard.cost_summary') }}
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Podział kosztów wg modeli -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">Wydatki wg modeli</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                                <th class="pb-3">{{ __('dashboard.table_model') }}</th>
                                <th class="pb-3 text-right">{{ __('dashboard.table_calls') }}</th>
                                <th class="pb-3 text-right">{{ __('dashboard.table_total_tokens') }}</th>
                                <th class="pb-3 text-right">{{ __('dashboard.table_cost') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            @forelse($costs['by_model'] as $bm)
                                <tr>
                                    <td class="py-3 font-medium text-slate-900 dark:text-white">{{ $bm['model'] }}</td>
                                    <td class="py-3 text-right text-slate-600 dark:text-slate-300">{{ number_format($bm['calls']) }}</td>
                                    <td class="py-3 text-right text-slate-500">{{ number_format($bm['tokens']) }}</td>
                                    <td class="py-3 text-right font-bold text-indigo-600 dark:text-indigo-400">${{ number_format($bm['cost_usd'], 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-400 text-sm">Brak kosztów w wybranym okresie.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabela konfiguracji cennika model_pricing -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">Cennik bazowy (za 1M tokenów)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                                <th class="pb-3">Wzorzec modelu</th>
                                <th class="pb-3 text-right">Wejście ($/1M)</th>
                                <th class="pb-3 text-right">Wyjście ($/1M)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                            @forelse($pricing as $p)
                                <tr>
                                    <td class="py-3 font-mono text-xs text-slate-800 dark:text-slate-200">{{ $p->model_pattern }}</td>
                                    <td class="py-3 text-right font-medium text-slate-600 dark:text-slate-300">${{ number_format((float) $p->input_cost_per_million, 4) }}</td>
                                    <td class="py-3 text-right font-medium text-slate-600 dark:text-slate-300">${{ number_format((float) $p->output_cost_per_million, 4) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-6 text-center text-slate-400 text-sm">Brak skonfigurowanych cenników.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
