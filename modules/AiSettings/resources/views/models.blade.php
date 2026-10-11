<x-layouts.app active-module="ai-settings" active-subcategory="models" :title="__('ai.title')">
    <div class="space-y-6">
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

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('ai.models_title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Centralny rejestr modeli LLM, parametry domyślne, cennik tokenów oraz ochrona nadpisań parametrów.
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($providers->isNotEmpty())
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" type="button"
                                class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium text-sm transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            {{ __('ai.sync_models') }}
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-56 rounded-xl bg-white dark:bg-slate-800 shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-20">
                            <div class="px-3 py-1 text-xs font-semibold uppercase text-slate-400">Wybierz dostawcę:</div>
                            @foreach($providers as $p)
                                <form action="{{ route('ai-settings.providers.sync-models', $p) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-xs text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors flex items-center justify-between">
                                        <span>{{ $p->name }}</span>
                                        <span class="text-slate-400 font-mono text-[10px]">({{ $p->driver }})</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
                <button type="button"
                        onclick="document.getElementById('modal-add-model').classList.remove('hidden')"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
                    <span>+</span>
                    <span>{{ __('ai.add_model') }}</span>
                </button>
            </div>
        </div>

        <!-- Tabela modeli -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('ai.model_key') }}</th>
                            <th class="px-6 py-3.5">{{ __('ai.provider') }}</th>
                            <th class="px-6 py-3.5">{{ __('ai.capabilities') }}</th>
                            <th class="px-6 py-3.5">{{ __('ai.context_window') }}</th>
                            <th class="px-6 py-3.5">Cennik (1M tok.)</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($models as $model)
                            @php
                                $price = $pricing->get($model->model_key);
                                $hasOverrides = !empty($model->overrides);
                            @endphp
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <div class="font-mono text-xs">{{ $model->model_key }}</div>
                                        @if($model->is_manual)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300">Ręczny</span>
                                        @endif
                                        @if($hasOverrides)
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300" title="Pola chronione: {{ implode(', ', $model->overrides) }}">Nadpisany</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ $model->display_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">
                                        {{ $model->provider->name ?? 'Brak' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($model->capabilities ?? ['chat'] as $cap)
                                            <span class="px-1.5 py-0.5 text-[10px] font-mono rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                {{ $cap }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs">
                                    {{ number_format($model->context_window) }} tok.
                                </td>
                                <td class="px-6 py-4 text-xs font-mono">
                                    @if($price)
                                        <span class="text-emerald-600 dark:text-emerald-400">${{ $price->input_cost_per_million }}</span> /
                                        <span class="text-indigo-600 dark:text-indigo-400">${{ $price->output_cost_per_million }}</span>
                                    @else
                                        <span class="text-slate-400">0.00 / 0.00</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($model->enabled)
                                        <span class="inline-flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktywny
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs text-slate-400 font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Wyłączony
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($hasOverrides)
                                            <form action="{{ route('ai-settings.models.reset', $model) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz przywrócić wartości domyślne dostawcy?')">
                                                @csrf
                                                <button type="submit" class="text-xs text-amber-600 dark:text-amber-400 hover:underline">
                                                    Reset
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button"
                                                onclick="openEditModal({{ json_encode($model) }}, {{ json_encode($price) }})"
                                                class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                            Edytuj
                                        </button>
                                        <form action="{{ route('ai-settings.models.destroy', $model) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć model?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-rose-600 dark:text-rose-400 hover:underline">
                                                Usuń
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                    Brak zarejestrowanych modeli. Skorzystaj z opcji „Synchronizuj z dostawcą” lub dodaj model ręcznie.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($models->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $models->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal: Dodaj model -->
    <div id="modal-add-model" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full border border-slate-200 dark:border-slate-800 shadow-2xl p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Dodaj model do rejestru</h3>
            <form action="{{ route('ai-settings.models.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Dostawca</label>
                    <select name="provider_id" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->driver }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Klucz modelu (ID)</label>
                        <input type="text" name="model_key" placeholder="np. gpt-4o" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nazwa wyświetlana</label>
                        <input type="text" name="display_name" placeholder="np. GPT-4o" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Okno kontekstu</label>
                        <input type="number" name="context_window" value="128000" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Maks. output tokens</label>
                        <input type="number" name="max_output_tokens" value="4096" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cena wejścia / 1M USD</label>
                        <input type="number" step="0.0001" name="input_cost_per_million" value="0.0" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cena wyjścia / 1M USD</label>
                        <input type="number" step="0.0001" name="output_cost_per_million" value="0.0" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('modal-add-model').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-300">Anuluj</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">Zapisz model</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edytuj model -->
    <div id="modal-edit-model" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 hidden">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full border border-slate-200 dark:border-slate-800 shadow-2xl p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Edycja parametrów i cennika</h3>
            <form id="form-edit-model" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Nazwa wyświetlana</label>
                        <input type="text" id="edit-display-name" name="display_name" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                        <div class="flex items-center mt-2">
                            <input type="checkbox" id="edit-enabled" name="enabled" value="1" class="rounded border-slate-300 text-indigo-600">
                            <label for="edit-enabled" class="ml-2 text-sm text-slate-700 dark:text-slate-300">Włączony</label>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Okno kontekstu</label>
                        <input type="number" id="edit-context-window" name="context_window" required class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Maks. output tokens</label>
                        <input type="number" id="edit-max-output-tokens" name="max_output_tokens" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cena wejścia / 1M USD</label>
                        <input type="number" step="0.0001" id="edit-input-cost" name="input_cost_per_million" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cena wyjścia / 1M USD</label>
                        <input type="number" step="0.0001" id="edit-output-cost" name="output_cost_per_million" class="w-full text-sm rounded-xl border-slate-200 dark:border-slate-800 dark:bg-slate-800">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" onclick="document.getElementById('modal-edit-model').classList.add('hidden')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-300">Anuluj</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">Zapisz zmiany</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(model, price) {
            document.getElementById('form-edit-model').action = '/ai-settings/models/' + model.id;
            document.getElementById('edit-display-name').value = model.display_name || '';
            document.getElementById('edit-enabled').checked = !!model.enabled;
            document.getElementById('edit-context-window').value = model.context_window || 4096;
            document.getElementById('edit-max-output-tokens').value = model.max_output_tokens || '';
            document.getElementById('edit-input-cost').value = price ? price.input_cost_per_million : 0;
            document.getElementById('edit-output-cost').value = price ? price.output_cost_per_million : 0;
            document.getElementById('modal-edit-model').classList.remove('hidden');
        }
    </script>
</x-layouts.app>
