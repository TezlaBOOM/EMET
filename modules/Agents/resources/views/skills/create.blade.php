<x-layouts.app active-module="agents" active-subcategory="skills" title="Dodaj Skill">
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Nowy Skill
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zarejestruj narzędzie, prompt lub prześlij paczkę ZIP z manifestem `skill.json`.
            </p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs" x-data="{ mode: 'manual' }">
            <div class="flex gap-4 border-b border-slate-200 dark:border-slate-800 pb-4 mb-6">
                <button type="button" @click="mode = 'manual'"
                        :class="mode === 'manual' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold' : 'text-slate-500'"
                        class="pb-2 text-sm px-2">
                    Formularz ręczny
                </button>
                <button type="button" @click="mode = 'zip'"
                        :class="mode === 'zip' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold' : 'text-slate-500'"
                        class="pb-2 text-sm px-2">
                    Paczka ZIP
                </button>
            </div>

            <form method="POST" action="{{ route('agents.skills.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="source_type" :value="mode">

                <template x-if="mode === 'zip'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Plik archiwum (.zip)
                            </label>
                            <input type="file" name="package" accept=".zip"
                                   class="w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <p class="text-xs text-slate-400 mt-1">Maksymalny rozmiar 20MB. Archiwum musi zawierać plik `skill.json`.</p>
                        </div>
                    </div>
                </template>

                <template x-if="mode === 'manual'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nazwa</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Typ</label>
                            <select name="type" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                                <option value="tool">Tool</option>
                                <option value="mcp">MCP Server</option>
                                <option value="prompt">Prompt</option>
                                <option value="workflow">Workflow</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Opis</label>
                            <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Dokumentacja README (Markdown)</label>
                            <textarea name="readme_md" rows="5" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-xs"></textarea>
                        </div>
                    </div>
                </template>

                <div class="pt-4 flex justify-end gap-2">
                    <a href="{{ route('agents.skills.index') }}" class="px-4 py-2 border rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400">
                        Anuluj
                    </a>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md">
                        Zapisz skill
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
