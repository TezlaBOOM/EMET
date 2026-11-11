<x-layouts.app active-module="logs" active-subcategory="traces" :title="__('logs.traces_title')">
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('logs.traces_title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('logs.traces_subtitle') }}
                </p>
            </div>

            <form method="GET" action="{{ route('logs.traces') }}" class="flex items-center gap-2">
                <select name="trace_id" onchange="this.form.submit()"
                        class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                    @forelse($recentTraceIds as $traceId)
                        <option value="{{ $traceId }}" @selected($traceId === $selectedTraceId)>
                            Trace: {{ $traceId }}
                        </option>
                    @empty
                        <option value="">Brak zarejestrowanych śladów OpenTelemetry</option>
                    @endforelse
                </select>
            </form>
        </div>

        @if($waterfall && $waterfall['span_count'] > 0)
            <!-- Podsumowanie diagnostyczne Trace'a -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4">
                    <div class="text-xs text-slate-400">Trace ID</div>
                    <div class="text-sm font-mono font-bold text-slate-900 dark:text-white truncate mt-1">
                        {{ $waterfall['trace_id'] }}
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4">
                    <div class="text-xs text-slate-400">Łączny czas (Waterfall)</div>
                    <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ $waterfall['total_duration_ms'] }} ms ({{ $waterfall['span_count'] }} spanów)
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4">
                    <div class="text-xs text-slate-400">Wąskie gardło (Bottleneck)</div>
                    <div class="text-xs font-mono font-semibold text-amber-600 dark:text-amber-400 mt-1">
                        @if($waterfall['bottleneck_span'])
                            {{ $waterfall['bottleneck_span']['name'] }} ({{ $waterfall['bottleneck_span']['duration_ms'] }} ms)
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4">
                    <div class="text-xs text-slate-400">Pierwotna przyczyna błędu (Root Cause)</div>
                    <div class="text-xs font-mono font-semibold {{ $waterfall['root_cause_error'] ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }} mt-1">
                        @if($waterfall['root_cause_error'])
                            {{ $waterfall['root_cause_error']['name'] }}: {{ $waterfall['root_cause_error']['error_message'] }}
                        @else
                            Brak błędów (OK)
                        @endif
                    </div>
                </div>
            </div>

            <!-- Wykres kaskadowy (Waterfall View) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Kaskada wywołań (OpenTelemetry Spans)
                    </h2>
                    <span class="text-xs font-mono text-slate-400">0 ms ⟶ {{ $waterfall['total_duration_ms'] }} ms</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($waterfall['spans'] as $span)
                        <div class="px-6 py-3.5 hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded font-mono uppercase text-[10px] font-bold
                                        {{ $span['status'] === 'error' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' : 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' }}">
                                        {{ $span['service_name'] }}
                                    </span>
                                    <span class="font-mono font-semibold text-slate-900 dark:text-white">
                                        {{ $span['name'] }}
                                    </span>
                                    @if($span['parent_span_id'])
                                        <span class="text-slate-400 font-mono text-[11px]">
                                            (parent: {{ $span['parent_span_id'] }})
                                        </span>
                                    @endif
                                </div>
                                <div class="font-mono text-slate-500">
                                    +{{ $span['offset_ms'] }} ms | <strong>{{ $span['duration_ms'] }} ms</strong> ({{ $span['width_pct'] }}%)
                                </div>
                            </div>

                            <!-- Pasek postępu Waterfall -->
                            <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden relative">
                                <div class="h-full rounded-full {{ $span['status'] === 'error' ? 'bg-rose-500' : 'bg-indigo-500' }}"
                                     style="margin-left: {{ min(95, $span['offset_pct']) }}%; width: {{ max(3, min(100 - $span['offset_pct'], $span['width_pct'])) }}%;"></div>
                            </div>

                            @if($span['error_message'])
                                <div class="text-xs font-mono text-rose-600 dark:text-rose-400">
                                    ⚠ {{ $span['error_message'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                Brak zarejestrowanych spanów OpenTelemetry dla wybranego śladu. Uruchom scenariusz lub wywołanie modelu, aby wygenerować drzewo kaskadowe.
            </div>
        @endif
    </div>
</x-layouts.app>

