<x-layouts.app active-module="agents" active-subcategory="skills" title="Magazyn Skilli">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Magazyn Skilli
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Biblioteka narzędzi, rozszerzeń MCP, promptów i paczek wykonawczych z wersjonowaniem.
                </p>
            </div>
            @can('skills.manage')
                <div class="flex gap-2">
                    <a href="{{ route('agents.skills.create') }}"
                       class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                        + Dodaj skill
                    </a>
                </div>
            @endcan
        </div>

        <!-- Filtry i wyszukiwarka -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
            <form method="GET" action="{{ route('agents.skills.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Szukaj po nazwie lub opisie..."
                       class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                <select name="type" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                    <option value="">Wszystkie typy</option>
                    <option value="tool" @selected(request('type') === 'tool')>Tool (narzędzie)</option>
                    <option value="mcp" @selected(request('type') === 'mcp')>MCP Server</option>
                    <option value="prompt" @selected(request('type') === 'prompt')>Prompt Template</option>
                    <option value="package" @selected(request('type') === 'package')>Paczka ZIP</option>
                </select>
                <select name="status" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                    <option value="">Wszystkie statusy</option>
                    <option value="active" @selected(request('status') === 'active')>Aktywny</option>
                    <option value="draft" @selected(request('status') === 'draft')>Szkic</option>
                    <option value="pending_review" @selected(request('status') === 'pending_review')>Oczekuje na przegląd</option>
                    <option value="deprecated" @selected(request('status') === 'deprecated')>Wycofany</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-700">
                    Filtruj
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($skills as $skill)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                {{ $skill->name }}
                            </h3>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                {{ $skill->type }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 font-mono">slug: {{ $skill->slug }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-3 line-clamp-3">
                            {{ $skill->description ?? 'Brak opisu.' }}
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-500 font-medium">
                            v{{ $skill->currentVersion?->version ?? '1.0.0' }}
                        </span>
                        <a href="{{ route('agents.skills.show', $skill) }}"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                            Szczegóły &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-500">
                    Brak skilli spełniających kryteria wyszukiwania.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $skills->links() }}
        </div>
    </div>
</x-layouts.app>
