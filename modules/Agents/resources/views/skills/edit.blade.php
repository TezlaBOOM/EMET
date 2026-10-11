<x-layouts.app active-module="agents" active-subcategory="skills" title="Edycja skilla: {{ $skill->name }}">
    <div class="max-w-4xl space-y-6">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <a href="{{ route('agents.skills.index') }}" class="hover:underline">Magazyn skilli</a>
                <span>/</span>
                <a href="{{ route('agents.skills.show', $skill) }}" class="hover:underline">{{ $skill->name }}</a>
                <span>/</span>
                <span>Edycja</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Edycja skilla: {{ $skill->name }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Aktualizacja metadanych oraz nowa wersja definicji (SemVer).
            </p>
        </div>

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('agents.skills.update', $skill) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Sekcja 1: Metadane -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">
                    1. Metadane podstawowe
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Nazwa skilla
                        </label>
                        <input type="text" name="name" value="{{ old('name', $skill->name) }}" required
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Identyfikator (slug)
                        </label>
                        <input type="text" disabled value="{{ $skill->slug }}"
                               class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/50 text-slate-500 text-sm font-mono cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Krótki opis
                    </label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">{{ old('description', $skill->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Typ skilla
                        </label>
                        <select name="type" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="tool" @selected($skill->type === 'tool')>Tool (narzędzie)</option>
                            <option value="mcp" @selected($skill->type === 'mcp')>MCP Server</option>
                            <option value="prompt" @selected($skill->type === 'prompt')>Prompt Template</option>
                            <option value="workflow" @selected($skill->type === 'workflow')>Workflow</option>
                            <option value="package" @selected($skill->type === 'package')>Paczka ZIP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Status
                        </label>
                        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="active" @selected($skill->status === 'active')>Aktywny</option>
                            <option value="draft" @selected($skill->status === 'draft')>Szkic</option>
                            <option value="pending_review" @selected($skill->status === 'pending_review')>Oczekuje na przegląd</option>
                            <option value="deprecated" @selected($skill->status === 'deprecated')>Wycofany</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Dokumentacja README (Markdown)
                    </label>
                    <textarea name="readme_md" rows="5" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">{{ old('readme_md', $skill->readme_md) }}</textarea>
                </div>
            </div>

            <!-- Sekcja 2: Definicja i wersjonowanie SemVer -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">
                        2. Definicja wykonawcza i wersja (SemVer)
                    </h3>
                    <span class="px-2.5 py-1 rounded-full text-xs font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Aktualna wersja: v{{ $skill->currentVersion?->version ?? '1.0.0' }}
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Definicja JSON (opcjonalnie nowa wersja)
                    </label>
                    <textarea name="definition" rows="6" placeholder='{"entry": "handler.php", "parameters": {}}'
                              class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">{{ old('definition', $skill->currentVersion?->definition ? json_encode($skill->currentVersion->definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">
                        Wprowadzenie zmian w definicji automatycznie utworzy nową wersję SemVer.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Typ podbicia wersji
                        </label>
                        <select name="bump_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="patch">Patch (poprawka, np. 1.0.0 &rarr; 1.0.1)</option>
                            <option value="minor">Minor (nowa funkcja, np. 1.0.0 &rarr; 1.1.0)</option>
                            <option value="major">Major (zmiana przełamująca, np. 1.0.0 &rarr; 2.0.0)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Opis zmian (Changelog)
                        </label>
                        <input type="text" name="changelog" placeholder="np. Dodano obsługę nowych parametrów"
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('agents.skills.show', $skill) }}"
                   class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    Anuluj
                </a>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                    Zapisz zmiany
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
