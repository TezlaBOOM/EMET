<x-layouts.app active-module="integrations" :active-subcategory="$activeSubcategory" :title="__('integrations.title')">
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

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('integrations.title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Orkiestracja instancji agentów zewnętrznych (Hermes, OpenClaw, Claude Code, Codex).
                </p>
            </div>

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('integrations.reconcile') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition-all inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Uruchom pętlę Reconcile
                    </button>
                </form>

                <button type="button"
                        onclick="document.getElementById('modal-add-instance').classList.remove('hidden')"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all">
                    + {{ __('integrations.provision_instance') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($instances as $instance)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                    {{ $instance->name }}
                                </h3>
                                <span class="text-xs text-slate-400 font-mono">
                                    {{ $instance->slug }}
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                @if($instance->status === 'running') bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800
                                @elseif($instance->status === 'provisioning') bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 animate-pulse
                                @else bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 @endif">
                                {{ $instance->status }}
                            </span>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Typ środowiska:</span>
                                <span class="font-mono uppercase font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ $instance->type }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Tryb wykonania:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200 capitalize">
                                    {{ $instance->mode }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Port usługi:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200">
                                    {{ $instance->port ? ':' . $instance->port : 'brak' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Endpoint:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200 truncate max-w-[160px]">
                                    {{ $instance->endpoint_url ?: 'oczekuje' }}
                                </span>
                            </div>
                            @if($instance->last_error)
                                <div class="mt-2 p-2 rounded-lg bg-rose-50 dark:bg-rose-950/50 text-[11px] text-rose-600 dark:text-rose-400">
                                    {{ Str::limit($instance->last_error, 80) }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-[10px] text-slate-400 font-mono">
                            Zaktualizowano: {{ $instance->updated_at->diffForHumans() }}
                        </span>

                        <form method="POST" action="{{ route('integrations.destroy', $instance) }}" onsubmit="return confirm('Usunąć tę instancję?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-rose-600 hover:underline">
                                Usuń
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <p class="text-slate-400 text-sm mb-3">
                        Brak skonfigurowanych instancji zewnętrznych.
                    </p>
                    <button type="button" onclick="document.getElementById('modal-add-instance').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">
                        + {{ __('integrations.provision_instance') }}
                    </button>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal dodawania nowej instancji -->
    <div id="modal-add-instance" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                {{ __('integrations.provision_instance') }}
            </h2>
            <form method="POST" action="{{ route('integrations.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('integrations.instance_name') }}
                    </label>
                    <input type="text" name="name" required placeholder="np. Hermes Production Unit 1" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.runtime_type') }}
                        </label>
                        <select name="type" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="hermes">Hermes Agent</option>
                            <option value="openclaw">OpenClaw</option>
                            <option value="claude_code">Claude Code</option>
                            <option value="codex">Codex</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.execution_mode') }}
                        </label>
                        <select name="mode" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="systemd">systemd (Debian 13 Native)</option>
                            <option value="docker">Docker Container</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Port usługi (opcjonalny - automatyczna alokacja z puli 8100-8900)
                    </label>
                    <input type="number" name="port" placeholder="Automatyczny" min="1024" max="65535" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-add-instance').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                        Uruchom provisioning
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
