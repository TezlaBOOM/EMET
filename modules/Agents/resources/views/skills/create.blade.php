<x-layouts.app active-module="agents" active-subcategory="skills" title="Dodaj Skill">
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Nowy Skill
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zarejestruj narzędzie, prompt lub prześlij paczkę ZIP z manifestem <code class="text-xs bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">skill.json</code>.
            </p>
        </div>

        @if ($errors->any())
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm space-y-1">
                <div class="font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    Wystąpiły błędy podczas dodawania skilla:
                </div>
                <ul class="list-disc list-inside text-xs space-y-0.5 ml-6">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-900 text-red-700 dark:text-red-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs"
             x-data="{
                 mode: '{{ old('source_type', 'manual') }}',
                 setMode(m) {
                     this.mode = m;
                     if (window.syncSkillForm) window.syncSkillForm(m);
                 }
             }"
             x-init="$nextTick(() => { if (window.syncSkillForm) window.syncSkillForm(mode); })">
            
            <!-- Zakładki wyboru metody -->
            <div class="flex gap-4 border-b border-slate-200 dark:border-slate-800 pb-4 mb-6">
                <button type="button"
                        id="tab-btn-manual"
                        @click="setMode('manual')"
                        onclick="window.switchSkillTab('manual')"
                        :class="mode === 'manual' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold dark:text-indigo-400 dark:border-indigo-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="pb-2 text-sm px-2 transition-colors {{ old('source_type', 'manual') === 'manual' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold dark:text-indigo-400 dark:border-indigo-400' : 'text-slate-500' }}">
                    Formularz ręczny
                </button>
                <button type="button"
                        id="tab-btn-zip"
                        @click="setMode('zip')"
                        onclick="window.switchSkillTab('zip')"
                        :class="mode === 'zip' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold dark:text-indigo-400 dark:border-indigo-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="pb-2 text-sm px-2 transition-colors {{ old('source_type') === 'zip' ? 'text-indigo-600 border-b-2 border-indigo-600 font-bold dark:text-indigo-400 dark:border-indigo-400' : 'text-slate-500' }}">
                    Paczka ZIP
                </button>
            </div>

            <form method="POST" action="{{ route('agents.skills.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="source_type" id="source_type" value="{{ old('source_type', 'manual') }}">

                <!-- Sekcja Paczka ZIP -->
                <div id="panel-zip"
                     x-show="mode === 'zip'"
                     :class="{ 'hidden': mode !== 'zip' }"
                     class="space-y-4 {{ old('source_type') === 'zip' ? '' : 'hidden' }}">
                    <div>
                        <label for="package_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Plik archiwum (.zip) <span class="text-red-500">*</span>
                        </label>
                        <input type="file"
                               name="package"
                               id="package_input"
                               accept=".zip,application/zip,application/x-zip-compressed,multipart/x-zip"
                               class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950/60 dark:file:text-indigo-300 cursor-pointer" />
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                            Maksymalny rozmiar 20MB. Archiwum może zawierać manifest <code class="text-xs bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">skill.json</code> z konfiguracją parametrów i uprawnień.
                        </p>
                    </div>
                </div>

                <!-- Sekcja Formularz ręczny -->
                <div id="panel-manual"
                     x-show="mode === 'manual'"
                     :class="{ 'hidden': mode !== 'manual' }"
                     class="space-y-4 {{ old('source_type', 'manual') === 'manual' ? '' : 'hidden' }}">
                    <div>
                        <label for="name_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Nazwa skilla <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name_input"
                               value="{{ old('name') }}"
                               placeholder="np. Web Page Reader"
                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div>
                        <label for="type_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Typ skilla <span class="text-red-500">*</span>
                        </label>
                        <select name="type"
                                id="type_input"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="tool" @selected(old('type', 'tool') === 'tool')>Tool (Narzędzie wywoływane przez model)</option>
                            <option value="mcp" @selected(old('type') === 'mcp')>MCP Server (Protokół MCP)</option>
                            <option value="prompt" @selected(old('type') === 'prompt')>Prompt (Szablon promptu)</option>
                            <option value="workflow" @selected(old('type') === 'workflow')>Workflow (Złożony scenariusz kroków)</option>
                            <option value="package" @selected(old('type') === 'package')>Package (Paczka kodu zewnętrznego)</option>
                        </select>
                    </div>

                    <div>
                        <label for="description_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Krótki opis
                        </label>
                        <textarea name="description"
                                  id="description_input"
                                  rows="2"
                                  placeholder="Wyjaśnij cel i możliwości tego skilla..."
                                  class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('description') }}</textarea>
                    </div>

                    <div>
                        <label for="readme_md_input" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Dokumentacja README (Markdown)
                        </label>
                        <textarea name="readme_md"
                                  id="readme_md_input"
                                  rows="5"
                                  placeholder="# Instrukcja skilla&#10;&#10;Podaj parametry wejściowe oraz przykłady..."
                                  class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white font-mono text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">{{ old('readme_md') }}</textarea>
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-200 dark:border-slate-800">
                    <a href="{{ route('agents.skills.index') }}"
                       class="px-4 py-2 border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 transition-colors">
                        Anuluj
                    </a>
                    <button type="submit"
                            id="submit_btn"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md shadow-indigo-600/20 transition-all">
                        Zapisz skill
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.syncSkillForm = function(mode) {
            var isManual = (mode === 'manual');
            var panelManual = document.getElementById('panel-manual');
            var panelZip = document.getElementById('panel-zip');
            var sourceInput = document.getElementById('source_type');

            if (sourceInput) {
                sourceInput.value = mode;
            }

            if (panelManual) {
                panelManual.classList.toggle('hidden', !isManual);
                var manualInputs = panelManual.querySelectorAll('input, select, textarea');
                manualInputs.forEach(function(el) {
                    el.disabled = !isManual;
                    if (el.name === 'name' || el.name === 'type') {
                        el.required = isManual;
                    }
                });
            }

            if (panelZip) {
                panelZip.classList.toggle('hidden', isManual);
                var zipInputs = panelZip.querySelectorAll('input, select, textarea');
                zipInputs.forEach(function(el) {
                    el.disabled = isManual;
                    if (el.name === 'package') {
                        el.required = !isManual;
                    }
                });
            }

            var tabManual = document.getElementById('tab-btn-manual');
            var tabZip = document.getElementById('tab-btn-zip');
            var activeClasses = ['text-indigo-600', 'border-b-2', 'border-indigo-600', 'font-bold', 'dark:text-indigo-400', 'dark:border-indigo-400'];
            var inactiveClasses = ['text-slate-500'];

            if (tabManual && tabZip) {
                if (isManual) {
                    activeClasses.forEach(function(c) { tabManual.classList.add(c); tabZip.classList.remove(c); });
                    tabManual.classList.remove('text-slate-500');
                    tabZip.classList.add('text-slate-500');
                } else {
                    activeClasses.forEach(function(c) { tabZip.classList.add(c); tabManual.classList.remove(c); });
                    tabZip.classList.remove('text-slate-500');
                    tabManual.classList.add('text-slate-500');
                }
            }
        };

        window.switchSkillTab = function(mode) {
            window.syncSkillForm(mode);
        };

        document.addEventListener('DOMContentLoaded', function() {
            var initialMode = document.getElementById('source_type') ? document.getElementById('source_type').value : 'manual';
            window.syncSkillForm(initialMode || 'manual');
        });
    </script>
</x-layouts.app>
