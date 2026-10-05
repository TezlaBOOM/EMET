<x-layouts.app active-module="dashboard" active-subcategory="overview" :title="__('dashboard.title')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('dashboard.title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('dashboard.sub_overview') }} – Projekt-Emet Telemetry & Orchestration Platform
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-600/20">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>System Online</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Kafelki telemetrii placeholder dla Etapu 1 -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Aktywni Agenci
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    0
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Zużycie tokenów (24h)
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    0
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Średnia latencja (TTFT)
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    -- ms
                </div>
            </div>

            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Szacowany koszt (USD)
                </div>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-2">
                    $0.00
                </div>
            </div>
        </div>
    </div>
</x-layout.app>
