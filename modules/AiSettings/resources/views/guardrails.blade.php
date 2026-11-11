<x-layouts.app active-module="ai-settings" active-subcategory="guardrails" :title="__('ai.guardrails_title')">
    <div class="space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('ai.guardrails_title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('ai.guardrails_subtitle') }}
                </p>
            </div>

            <form method="POST" action="{{ route('ai-settings.semantic-cache.flush') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-500 text-white shadow-xs transition">
                    {{ __('ai.flush_semantic_cache') }}
                </button>
            </form>
        </div>

        <!-- Karty statystyk Semantic Cache & Guardrails -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.cache_entries_count') }}</span>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $cacheStats['entries_count'] }}</div>
                <span class="text-xs text-slate-400">Próg: {{ number_format($cacheStats['similarity_threshold'] * 100, 0) }}%</span>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.cache_total_hits') }}</span>
                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $cacheStats['total_hits'] }}</div>
                <span class="text-xs text-slate-400">TTL: {{ (int) ($cacheStats['ttl_seconds'] / 3600) }}h</span>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.cache_saved_tokens') }}</span>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($cacheStats['saved_tokens']) }}</div>
                <span class="text-xs text-slate-400">Zero opóźnień LLM</span>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.cache_saved_cost') }}</span>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">${{ number_format($cacheStats['saved_cost_usd'], 4) }}</div>
                <span class="text-xs text-slate-400">Koszt $0.00 / hit</span>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.guardrails_blocked_count') }}</span>
                <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ $guardrailStats['blocked_count'] }}</div>
                <span class="text-xs text-slate-400">Tryb: {{ $guardrailStats['jailbreak_action'] }}</span>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">{{ __('ai.guardrails_masked_items') }}</span>
                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $guardrailStats['total_masked_items'] }}</div>
                <span class="text-xs text-slate-400">PESEL, Email, Karty, API</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Ostatnie zdarzenia AI Guardrails & DLP -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">
                        {{ __('ai.recent_guardrail_events') }}
                    </h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentEvents as $event)
                        <div class="p-4 text-xs flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full font-semibold {{ $event->action_taken === 'blocked' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                        {{ $event->event_type }}
                                    </span>
                                    <span class="font-mono text-slate-500">{{ implode(', ', $event->matched_rules ?? []) }}</span>
                                </div>
                                @if($event->prompt_excerpt)
                                    <p class="text-slate-600 dark:text-slate-300 font-mono truncate max-w-md">{{ $event->prompt_excerpt }}</p>
                                @endif
                            </div>
                            <span class="text-slate-400 whitespace-nowrap">{{ $event->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-slate-400">
                            {{ __('ai.no_guardrail_events') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Ostatnie wpisy w Semantycznej Pamięci Podręcznej -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="font-bold text-slate-900 dark:text-white text-base">
                        {{ __('ai.recent_cache_entries') }}
                    </h3>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($recentCacheEntries as $entry)
                        <div class="p-4 text-xs flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-full font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        {{ $entry->model }}
                                    </span>
                                    <span class="text-emerald-600 font-semibold">Hits: {{ $entry->hit_count }}</span>
                                    <span class="text-slate-400">Zaoszczędzono: {{ $entry->saved_tokens }} tok.</span>
                                </div>
                                <p class="text-slate-600 dark:text-slate-300 truncate max-w-md">{{ $entry->prompt_text }}</p>
                            </div>
                            <span class="text-slate-400 whitespace-nowrap">{{ $entry->updated_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-slate-400">
                            {{ __('ai.no_cache_entries') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

