<x-layouts.app active-module="agents" active-subcategory="create" :title="__('agents.sub_create')">
    <div class="max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('agents.sub_create') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zdefiniuj tożsamość, instrukcję systemową, środowisko uruchomieniowe oraz parametry modelu.
            </p>
        </div>

        <form method="POST" action="{{ route('agents.store') }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-xs">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.name') }}
                    </label>
                    <input type="text" name="name" value="{{ old('name', request('name')) }}" required placeholder="np. Badacz Wiedzy" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.slug') }} (opcjonalny)
                    </label>
                    <input type="text" name="slug" value="{{ old('slug') }}" placeholder="np. badacz-wiedzy" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('agents.description') }}
                </label>
                <input type="text" name="description" value="{{ old('description', request('description')) }}" placeholder="Krótki opis specjalizacji agenta" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.runtime_type') }}
                    </label>
                    <select name="runtime_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        @php $rt = old('runtime_type', request('runtime_type', 'native')); @endphp
                        <option value="native" @selected($rt === 'native')>Native (Wbudowany)</option>
                        <option value="hermes" @selected($rt === 'hermes')>Hermes Agent</option>
                        <option value="openclaw" @selected($rt === 'openclaw')>OpenClaw</option>
                        <option value="claude_code" @selected($rt === 'claude_code')>Claude Code</option>
                        <option value="codex" @selected($rt === 'codex')>Codex</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.pool') }}
                    </label>
                    <select name="pool_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        <option value="">Domyślna (Wszystkie aktywne konta)</option>
                        @foreach($pools as $p)
                            <option value="{{ $p->id }}" @selected(old('pool_id') == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.primary_model') }}
                    </label>
                    <input type="text" name="primary_model" value="{{ old('primary_model', request('primary_model', 'gpt-4o')) }}" required placeholder="np. gpt-4o, claude-3-5-sonnet" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('agents.temperature') }}: <span id="temp-val" class="font-mono text-indigo-600 font-bold">0.70</span>
                    </label>
                </div>
                <input type="range" name="temperature" min="0" max="2" step="0.05" value="{{ old('temperature', request('temperature', 0.70)) }}"
                       oninput="document.getElementById('temp-val').innerText = parseFloat(this.value).toFixed(2)"
                       class="w-full accent-indigo-600 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('agents.system_prompt') }}
                </label>
                <textarea name="system_prompt" rows="5" placeholder="Wpisz instrukcję systemową określającą zachowanie, ton oraz ograniczenia agenta..." class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">{{ old('system_prompt', request('system_prompt')) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Agent aktywny i gotowy do przyjmowania zadań
                </label>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('agents.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                    {{ __('common.cancel') }}
                </a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-md shadow-indigo-600/20">
                    {{ __('common.save') }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
