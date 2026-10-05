<x-layouts.app active-module="memory" active-subcategory="collections" :title="__('memory.sub_collections')">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('memory.sub_collections') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Zarządzanie indeksami i kolekcjami w bazach Qdrant oraz pgvector.
                </p>
            </div>

            @can('memory.manage')
                <button type="button"
                        onclick="document.getElementById('modal-add-collection').classList.remove('hidden')"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                    + {{ __('memory.create_collection') }}
                </button>
            @endcan
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($collections as $col)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                    {{ $col->name }}
                                </h3>
                                <span class="text-xs text-slate-400 font-mono">
                                    {{ $col->slug }}
                                </span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded uppercase text-[10px] font-bold font-mono bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ $col->vector_store }}
                            </span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/50">
                                <span class="text-slate-500">Model embeddingów:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200 font-mono">{{ $col->embedding_model }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/50">
                                <span class="text-slate-500">Wymiarowość:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200 font-mono">{{ $col->dimensions }} dim</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800/50">
                                <span class="text-slate-500">Metryka odległości:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200 capitalize">{{ $col->distance_metric }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500">Liczba fragmentów (Chunks):</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                                    {{ $col->chunks_count }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <a href="{{ route('memory.index', ['collection_id' => $col->id]) }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Eksploruj dokumenty &rarr;
                        </a>

                        @can('memory.manage')
                            <form method="POST" action="{{ route('memory.collections.destroy', $col) }}" onsubmit="return confirm('Usunąć tę kolekcję wraz ze wszystkimi wektorami?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-600 hover:underline">
                                    Usuń
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="col-span-full p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <p class="text-slate-400 text-sm">
                        Brak utworzonych kolekcji wektorowych.
                    </p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Modal nowej kolekcji -->
    <div id="modal-add-collection" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                {{ __('memory.create_collection') }}
            </h2>
            <form method="POST" action="{{ route('memory.collections.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('memory.collection_name') }}
                    </label>
                    <input type="text" name="name" required placeholder="np. Dokumentacja Techniczna" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('memory.vector_store') }}
                        </label>
                        <select name="vector_store" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            <option value="qdrant">Qdrant (Zalecany)</option>
                            <option value="pgvector">pgvector (PostgreSQL)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Dostawca LLM
                        </label>
                        <select name="embedding_provider_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                            @foreach($providers as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            Model embeddingów
                        </label>
                        <input type="text" name="embedding_model" value="text-embedding-3-small" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('memory.dimensions') }}
                        </label>
                        <input type="number" name="dimensions" value="1536" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('memory.distance_metric') }}
                    </label>
                    <select name="distance_metric" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        <option value="cosine">Cosine (Kosinusowa - zalecana)</option>
                        <option value="euclidean">Euclidean (Euklidesowa)</option>
                        <option value="dot">Dot Product (Iloczyn skalarny)</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-add-collection').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                        Utwórz kolekcję
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
