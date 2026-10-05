<x-layouts.app active-module="agents" active-subcategory="index" :title="__('agents.title')">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('agents.menu_title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Skonfigurowane instancje agentów, ich modele, pule kont i zestawy narzędzi (Skills).
                </p>
            </div>
            @can('agents.manage')
                <div class="flex gap-2">
                    <a href="{{ route('agents.templates') }}"
                       class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium text-sm hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        {{ __('agents.sub_templates') }}
                    </a>
                    <a href="{{ route('agents.create') }}"
                       class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                        + {{ __('agents.sub_create') }}
                    </a>
                </div>
            @endcan
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($agents as $agent)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                    {{ $agent->name }}
                                </h3>
                                <span class="text-xs text-slate-400 font-mono">
                                    {{ $agent->slug }}
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                {{ $agent->is_active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                {{ $agent->is_active ? __('agents.active') : __('agents.inactive') }}
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-slate-600 dark:text-slate-300 line-clamp-2">
                            {{ $agent->description ?: 'Brak opisu dla tego agenta.' }}
                        </p>

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Runtime:</span>
                                <span class="font-mono uppercase font-semibold text-indigo-600 dark:text-indigo-400">
                                    {{ $agent->runtime_type }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Model:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $agent->primary_model }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Pula kont:</span>
                                <span class="text-slate-700 dark:text-slate-300">
                                    {{ $agent->pool?->name ?? 'Domyślna' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-slate-500">Aktywne skille:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-medium">
                                    {{ $agent->skills->where('is_enabled', true)->count() }} włączone
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <a href="{{ route('chat.index', ['agent_id' => $agent->id]) }}"
                           class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                            {{ __('agents.chat_with_agent') }} &rarr;
                        </a>

                        @can('agents.manage')
                            <div class="flex items-center gap-2">
                                <a href="{{ route('agents.edit', $agent) }}" class="text-xs text-slate-500 hover:text-slate-900 dark:hover:text-white">
                                    Edytuj
                                </a>
                                <form method="POST" action="{{ route('agents.destroy', $agent) }}" class="inline" onsubmit="return confirm('Usunąć tego agenta?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-600 hover:underline">
                                        Usuń
                                    </button>
                                </form>
                            </div>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="col-span-full p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <p class="text-slate-400 text-sm">
                        Brak skonfigurowanych agentów.
                    </p>
                    <a href="{{ route('agents.create') }}" class="mt-3 inline-block px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">
                        + Utwórz pierwszego agenta
                    </a>
                </div>
            @endforelse
        </div>

        @if($agents->hasPages())
            <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                {{ $agents->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
