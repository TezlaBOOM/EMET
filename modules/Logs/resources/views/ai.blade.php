<x-layouts.app active-module="logs" active-subcategory="ai" :title="__('logs.title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('logs.sub_ai') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zdarzenia bramy LLM Gateway, przełączenia awaryjne (failover), błędy 429 i okresy cooldown.
            </p>
        </div>
        <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm text-slate-400">
            Zdarzenia wywołań modeli AI będą prezentowane po uruchomieniu serwisu bramy w Etapie 3.
        </div>
    </div>
</x-layouts.app>
