<x-layouts.app active-module="agents" active-subcategory="index" :title="__('agents.title')">
    <div class="max-w-3xl space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Edycja agenta: {{ $agent->name }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Modyfikuj parametry wykonawcze, instrukcję systemową oraz umiejętności (Skills).
                </p>
            </div>
            <a href="{{ route('chat.index', ['agent_id' => $agent->id]) }}"
               class="px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-semibold text-sm hover:bg-indigo-100 transition-all">
                {{ __('agents.chat_with_agent') }} &rarr;
            </a>
        </div>

        <form method="POST" action="{{ route('agents.update', $agent) }}" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-5 shadow-xs">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.name') }}
                    </label>
                    <input type="text" name="name" value="{{ old('name', $agent->name) }}" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.slug') }}
                    </label>
                    <input type="text" disabled value="{{ $agent->slug }}" class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/50 text-slate-500 text-sm font-mono cursor-not-allowed">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('agents.description') }}
                </label>
                <input type="text" name="description" value="{{ old('description', $agent->description) }}" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.runtime_type') }}
                    </label>
                    <select name="runtime_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        <option value="native" @selected($agent->runtime_type === 'native')>Native (Wbudowany)</option>
                        <option value="hermes" @selected($agent->runtime_type === 'hermes')>Hermes Agent</option>
                        <option value="openclaw" @selected($agent->runtime_type === 'openclaw')>OpenClaw</option>
                        <option value="claude_code" @selected($agent->runtime_type === 'claude_code')>Claude Code</option>
                        <option value="codex" @selected($agent->runtime_type === 'codex')>Codex</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.pool') }}
                    </label>
                    <select name="pool_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        <option value="">Domyślna (Wszystkie aktywne konta)</option>
                        @foreach($pools as $p)
                            <option value="{{ $p->id }}" @selected($agent->pool_id == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('agents.primary_model') }}
                    </label>
                    <input type="text" name="primary_model" value="{{ old('primary_model', $agent->primary_model) }}" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        {{ __('agents.temperature') }}: <span id="temp-val" class="font-mono text-indigo-600 font-bold">{{ number_format($agent->temperature, 2) }}</span>
                    </label>
                </div>
                <input type="range" name="temperature" min="0" max="2" step="0.05" value="{{ old('temperature', $agent->temperature) }}"
                       oninput="document.getElementById('temp-val').innerText = parseFloat(this.value).toFixed(2)"
                       class="w-full accent-indigo-600 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    {{ __('agents.system_prompt') }}
                </label>
                <textarea name="system_prompt" rows="5" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">{{ old('system_prompt', $agent->system_prompt) }}</textarea>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" @checked($agent->is_active) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <label for="is_active" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Agent aktywny
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

        <!-- Sekcja zarządzania skillami -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 space-y-4 shadow-xs">
            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                {{ __('agents.skills') }}
            </h3>
            <p class="text-xs text-slate-500">
                Włącz lub wyłącz narzędzia, które agent może autonomicznie wywoływać podczas konwersacji.
            </p>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach($agent->skills as $skill)
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-sm text-slate-900 dark:text-white">{{ $skill->name }}</span>
                            <span class="ml-2 text-xs font-mono text-slate-400">({{ $skill->driver }})</span>
                        </div>
                        <form method="POST" action="{{ route('agents.skills.toggle', [$agent, $skill]) }}">
                            @csrf
                            <button type="submit" class="px-3 py-1 rounded-lg text-xs font-semibold transition-colors
                                {{ $skill->is_enabled ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-400 hover:bg-slate-200' }}">
                                {{ $skill->is_enabled ? 'Włączony' : 'Wyłączony' }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
