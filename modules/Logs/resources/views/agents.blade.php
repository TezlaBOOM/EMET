<x-layouts.app active-module="logs" active-subcategory="agents" :title="__('logs.title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('logs.sub_agents') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Strumień aktywności agentów autonomicznych, użycia narzędzi i uruchomień zadań.
            </p>
        </div>
        <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm text-slate-400">
            Logi aktywności agentów będą rejestrowane w trakcie uruchamiania zadań w Etapie 4 i 6.
        </div>
    </div>
</x-layouts.app>
