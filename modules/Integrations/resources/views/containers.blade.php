<x-layouts.app active-module="integrations" active-subcategory="containers" title="Kontenery Docker">
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
                    Kontenery Docker & Adopcja
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Wykrywanie lokalnych kontenerów Docker, adopcja (observe, configure, managed) oraz auto-konfiguracja modeli.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('integrations.containers.detect') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Wykryj kontenery
                    </button>
                </form>
            </div>
        </div>

        @if(! $isSocketAvailable)
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-300 text-sm flex items-center justify-between">
                <span>Docker socket nie jest bezpośrednio dostępny na ścieżce systemowej. Moduł działa w trybie sterownika mock / fallback.</span>
            </div>
        @endif

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Wykryte kontenery ({{ count($containers) }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Nazwa / ID</th>
                            <th class="px-4 py-3">Obraz</th>
                            <th class="px-4 py-3">Wykryty typ</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Tryb adopcji</th>
                            <th class="px-4 py-3 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($containers as $c)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 font-mono">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $c->name }}</div>
                                    <div class="text-[10px] text-slate-400">{{ Str::limit($c->container_id, 16) }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px]">{{ $c->image }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $c->detected_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $c->status === 'running' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($c->adoption_mode === 'none')
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500" title="Nieadoptowany - nietykalny">
                                            Nieadoptowany (nietykalny)
                                        </span>
                                    @elseif($c->adoption_mode === 'observe')
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300">
                                            observe
                                        </span>
                                    @elseif($c->adoption_mode === 'configure')
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300">
                                            configure
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                                            managed (agenthub-net)
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-1">
                                    @if($c->adoption_mode === 'none')
                                        <form method="POST" action="{{ route('integrations.containers.adopt', $c) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="mode" value="configure">
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded bg-indigo-600 text-white font-medium hover:bg-indigo-500">
                                                Adoptuj (configure)
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('integrations.containers.adopt', $c) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="mode" value="managed">
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded bg-emerald-600 text-white font-medium hover:bg-emerald-500">
                                                Adoptuj (managed)
                                            </button>
                                        </form>
                                    @else
                                        @if($profiles->isNotEmpty())
                                            <form method="POST" action="{{ route('integrations.containers.autoconfig', $c) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="profile_id" value="{{ $profiles->first()->id }}">
                                                <button type="submit" class="px-2.5 py-1 text-xs rounded border border-indigo-300 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 font-medium hover:bg-indigo-50 dark:hover:bg-indigo-950/50">
                                                    Auto-konfiguracja
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('integrations.containers.release', $c) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                                                Zwolnij
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Brak wykrytych kontenerów. Kliknij „Wykryj kontenery”, aby przeskanować środowisko Docker.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
