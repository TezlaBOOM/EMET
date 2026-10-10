<x-layouts.app active-module="system" active-subcategory="transfers" title="Raport Dry-Run Importu">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Raport Walidacji i Podglądu (Dry-Run)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Wersja paczki: {{ $manifest['agenthub_version'] ?? '1.5.0' }} | Wyeksportowano: {{ $manifest['exported_at'] ?? 'b/d' }} | Tryb: <span class="font-bold">{{ $mode }}</span>
                </p>
            </div>
            <a href="{{ route('system.transfers.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-800">
                Wróć do transferów
            </a>
        </div>

        <div class="space-y-4">
            @foreach($diffReport as $sectionKey => $plan)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-slate-900 dark:text-white capitalize">Sekcja: {{ $sectionKey }}</h2>
                        <div class="flex gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                Nowe: {{ count($plan['create'] ?? []) }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                Aktualizacje: {{ count($plan['update'] ?? []) }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                Pominięte: {{ count($plan['skip'] ?? []) }}
                            </span>
                            @if(!empty($plan['conflicts']))
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                    Konflikty: {{ count($plan['conflicts']) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="bg-slate-950 text-slate-300 font-mono text-xs rounded-xl p-4 overflow-x-auto max-h-60">
                        <pre>{{ json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
