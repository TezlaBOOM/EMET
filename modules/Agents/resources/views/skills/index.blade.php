<x-layouts.app active-module="agents" active-subcategory="skills" title="Magazyn Skilli">
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
                    {{ $isTrash ? 'Kosz skilli' : 'Magazyn Skilli' }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ $isTrash ? 'Elementy usunięte z magazynu skilli z możliwością przywrócenia lub trwałego skasowania.' : 'Biblioteka narzędzi, rozszerzeń MCP, promptów i paczek wykonawczych z wersjonowaniem.' }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-medium">
                    <a href="{{ route('agents.skills.index') }}"
                       class="px-3 py-1.5 rounded-lg transition-all {{ !$isTrash ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        Aktywne
                    </a>
                    <a href="{{ route('agents.skills.index', ['trash' => 1]) }}"
                       class="px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 {{ $isTrash ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-2xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900' }}">
                        <span>Kosz</span>
                        @if($trashedCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-500 text-white font-bold">{{ $trashedCount }}</span>
                        @endif
                    </a>
                </div>

                @if(!$isTrash)
                    @can('skills.manage')
                        <a href="{{ route('agents.skills.create') }}"
                           class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1">
                            <span>+</span>
                            <span>Dodaj skill</span>
                        </a>
                    @endcan
                @endif
            </div>
        </div>

        <!-- Filtry i wyszukiwarka -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
            <form method="GET" action="{{ route('agents.skills.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @if($isTrash)
                    <input type="hidden" name="trash" value="1" />
                @endif
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
            @php
                $lifecycle = app(\App\Contracts\Skills\SkillLifecycleInterface::class);
            @endphp
            @forelse($skills as $skill)
                @php
                    $isUnused = !$isTrash && $lifecycle->isUnused($skill);
                @endphp
                <div class="bg-white dark:bg-slate-900 border {{ $isUnused ? 'border-rose-300 dark:border-rose-900/60 shadow-rose-500/5' : 'border-slate-200 dark:border-slate-800' }} rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                {{ $skill->name }}
                            </h3>
                            <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                @if($isUnused)
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex items-center gap-1"
                                          title="Skill nie jest powiązany z żadnym agentem ani scenariuszem. Decyzja o usunięciu należy do użytkownika.">
                                        🚩 Nieużywany
                                    </span>
                                @endif
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    {{ $skill->type }}
                                </span>
                            </div>
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

                        <div class="flex items-center gap-3">
                            @if($isTrash)
                                <form action="{{ route('agents.skills.restore', $skill->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-emerald-600 hover:text-emerald-500">
                                        Przywróć
                                    </button>
                                </form>
                                @can('skills.purge')
                                    <form action="{{ route('agents.skills.purge', $skill->id) }}" method="POST" onsubmit="return confirm('Czy na pewno chcesz bezpowrotnie usunąć ten skill i powiązane pliki?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                            Usuń trwale
                                        </button>
                                    </form>
                                @endcan
                            @else
                                <a href="{{ route('agents.skills.edit', $skill) }}"
                                   class="text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-indigo-600">
                                    Edytuj
                                </a>
                                <a href="{{ route('agents.skills.show', $skill) }}"
                                   class="text-xs font-semibold text-indigo-600 hover:text-indigo-500">
                                    Szczegóły &rarr;
                                </a>
                                @can('skills.delete')
                                    <form action="{{ route('agents.skills.destroy', $skill) }}" method="POST" onsubmit="return confirm('Czy przenieść ten skill do kosza?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                            Usuń
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-8 text-center bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 text-slate-500">
                    {{ $isTrash ? 'Kosz skilli jest pusty.' : 'Brak skilli spełniających kryteria wyszukiwania.' }}
                </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $skills->links() }}
        </div>
    </div>
</x-layouts.app>
