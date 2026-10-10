<x-layouts.app active-module="scenarios" active-subcategory="all" title="Scenariusze">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Scenariusze blokowe
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Wizualne projektowanie przepływów agentów, narzędzi i pamięci z telemetrią na żywo.
                </p>
            </div>
            @can('scenarios.manage')
                <div class="flex gap-2">
                    <a href="{{ route('scenarios.create') }}"
                       class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                        + Nowy scenariusz
                    </a>
                </div>
            @endcan
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($scenarios as $scenario)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                {{ $scenario->name }}
                            </h3>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $scenario->status === 'published' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                                {{ $scenario->status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1 font-mono">slug: {{ $scenario->slug }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-3 line-clamp-3">
                            {{ $scenario->description ?? 'Brak opisu.' }}
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-500 font-medium">
                            v{{ $scenario->currentVersion?->version ?? '1.0' }}
                        </span>
                        <a href="{{ route('scenarios.editor', $scenario) }}"
                           target="_blank"
                           class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 flex items-center gap-1">
                            Otwórz w osobnym oknie &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-500">
                    Brak utworzonych scenariuszy. Kliknij „Nowy scenariusz”, aby rozpocząć.
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $scenarios->links() }}
        </div>
    </div>
</x-layouts.app>
