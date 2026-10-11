<x-layouts.app active-module="scenarios" active-subcategory="all" :title="__('scenarios.edit_title') ?? 'Edycja scenariusza'">
    <div class="max-w-4xl mx-auto space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('scenarios.index') }}" class="hover:underline">Wszystkie scenariusze</a>
                <span>/</span>
                <span class="font-mono">{{ $scenario->slug }}</span>
                <span>/</span>
                <span>Edycja</span>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                        Edycja: {{ $scenario->name }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Zarządzanie metadanymi, wersjami grafu oraz stanem publikacji.
                    </p>
                <div class="flex items-center gap-2">
                    <a href="{{ route('scenarios.triggers.index', $scenario) }}"
                       class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-sm rounded-xl transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Harmonogramy</span>
                    </a>
                    <a href="{{ route('scenarios.editor', $scenario) }}"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition-all flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>Otwórz edytor grafu</span>
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <form method="POST" action="{{ route('scenarios.update', $scenario) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Nazwa scenariusza
                        </label>
                        <input type="text" name="name" value="{{ old('name', $scenario->name) }}" required
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Identyfikator (slug)
                        </label>
                        <input type="text" disabled value="{{ $scenario->slug }}"
                               class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/50 text-slate-500 text-sm font-mono cursor-not-allowed" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Opis scenariusza
                    </label>
                    <textarea name="description" rows="3"
                              class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">{{ old('description', $scenario->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Status
                        </label>
                        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                            <option value="draft" @selected($scenario->status === 'draft')>Szkic roboczy (Draft)</option>
                            <option value="published" @selected($scenario->status === 'published')>Opublikowany (Published)</option>
                            <option value="archived" @selected($scenario->status === 'archived')>Zarchiwizowany (Archived)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Aktualna wersja publikacji
                        </label>
                        <div class="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300 text-sm font-mono flex items-center justify-between">
                            <span>Wersja {{ $scenario->currentVersion?->version ?? 1 }}</span>
                            <span class="text-xs text-slate-400 font-sans">
                                {{ $scenario->currentVersion?->published_at ? $scenario->currentVersion->published_at->format('Y-m-d H:i') : 'Nieopublikowany' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('scenarios.index') }}"
                       class="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Anuluj
                    </a>
                    <button type="submit"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition-colors">
                        Zapisz zmiany
                    </button>
                </div>
            </form>
        </div>

        <!-- Historia wersji grafu -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                Historia wersji grafu
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Wersja</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Data utworzenia</th>
                            <th class="px-4 py-3">Changelog</th>
                            <th class="px-4 py-3 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($scenario->versions as $ver)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-mono font-semibold">
                                    v{{ $ver->version }}
                                    @if($scenario->current_version_id === $ver->id)
                                        <span class="ml-1.5 px-2 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-sans">Aktywna</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($ver->draft)
                                        <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Szkic roboczy</span>
                                    @else
                                        <span class="px-2 py-0.5 text-xs rounded bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">Opublikowana</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $ver->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $ver->changelog ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if(!$ver->draft && $scenario->current_version_id !== $ver->id)
                                        <form action="{{ route('scenarios.restore-version', [$scenario, $ver]) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz przywrócić tę archiwalną wersję?')">
                                            @csrf
                                            <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                                Przywróć
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
