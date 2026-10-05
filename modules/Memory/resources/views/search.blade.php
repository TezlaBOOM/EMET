<x-layouts.app active-module="memory" active-subcategory="search" :title="__('memory.sub_search')">
    <div class="space-y-6 max-w-4xl">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('memory.sub_search') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Wyszukuj informacje w bazie wiedzy za pomocą wektorów znaczeniowych (Cosine Similarity).
            </p>
        </div>

        <!-- Pasek wyszukiwania -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
            <form method="GET" action="{{ route('memory.search') }}" class="flex flex-col md:flex-row gap-3">
                @if($collections->isNotEmpty())
                    <select name="collection_id" class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold">
                        @foreach($collections as $col)
                            <option value="{{ $col->id }}" @selected($activeCollection?->id === $col->id)>
                                {{ $col->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <div class="flex-1 relative">
                    <input type="text"
                           name="q"
                           value="{{ $query }}"
                           placeholder="{{ __('memory.search_query') }}"
                           required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                    Szukaj
                </button>
            </form>
        </div>

        <!-- Wyniki wyszukiwania -->
        @if(! empty($query))
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    Wyniki wyszukiwania semantycznego ({{ count($results) }} znalezionych)
                </h3>

                @forelse($results as $res)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-2 hover:border-indigo-500/40 transition-all">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">
                                {{ $res['chunk']?->title ?? ($res['payload']['title'] ?? 'Dokument') }}
                            </h4>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold font-mono
                                @if($res['score'] >= 0.8) bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800
                                @elseif($res['score'] >= 0.5) bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800
                                @else bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 @endif">
                                {{ __('memory.similarity_score') }}: {{ number_format($res['score'] * 100, 1) }}%
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed font-sans">
                            {{ $res['chunk']?->content ?? ($res['payload']['content'] ?? '') }}
                        </p>
                    </div>
                @empty
                    <div class="p-8 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-xs">
                        Brak pasujących wyników dla zapytania "{{ $query }}".
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-layouts.app>
