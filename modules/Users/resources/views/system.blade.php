<x-layouts.app active-module="users" active-subcategory="system" :title="__('users.system_title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('users.system_title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('users.system_desc') }}
            </p>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200 whitespace-pre-wrap font-mono">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200 whitespace-pre-wrap font-mono">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Karta aktualnej wersji -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
                <div class="text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">
                    {{ __('users.current_version') }}
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                        v{{ $currentVersion }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                        Stable
                    </span>
                </div>
                <div class="pt-2">
                    <form action="{{ route('users.system.compat_check') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs shadow-xs transition">
                            {{ __('users.run_compat_check') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Informacja o aktualizacji CLI -->
            <div class="md:col-span-2 p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Procedura automatycznej aktualizacji</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Ze względów bezpieczeństwa i integralności proces aktualizacji kodu, bibliotek Composer, zasobów npm i migracji bazy danych przeprowadzany jest skryptem powłoki z automatycznym backupem:
                </p>
                <div class="bg-slate-900 text-slate-100 p-3 rounded-xl font-mono text-xs overflow-x-auto">
                    <code>./scripts/update.sh</code>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    W przypadku wykrycia błędu lub przerwania migracji, system automatycznie uruchamia procedurę przywracania:
                </p>
                <div class="bg-slate-900 text-slate-100 p-3 rounded-xl font-mono text-xs overflow-x-auto">
                    <code>./scripts/rollback.sh [opcjonalny_timestamp]</code>
                </div>
            </div>
        </div>

        <!-- Tabela backupów -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
                {{ __('users.backups') }}
            </h2>

            @if(empty($backups))
                <div class="text-center py-6 text-sm text-slate-400">
                    {{ __('users.no_backups') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-800 text-2xs font-semibold text-slate-400 uppercase">
                                <th class="pb-3">Identyfikator kopii</th>
                                <th class="pb-3">Data utworzenia</th>
                                <th class="pb-3">Ścieżka na dysku</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($backups as $b)
                                <tr>
                                    <td class="py-3 font-mono font-medium text-slate-900 dark:text-white">{{ $b['name'] }}</td>
                                    <td class="py-3 text-slate-500">{{ $b['created_at'] }}</td>
                                    <td class="py-3 font-mono text-xs text-slate-400">{{ $b['path'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
