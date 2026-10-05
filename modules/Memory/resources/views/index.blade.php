<x-layouts.app active-module="memory" active-subcategory="index" :title="__('memory.title')">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('memory.sub_explorer') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Przeglądaj zindeksowane fragmenty wiedzy i dokumenty semantyczne.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Wybór kolekcji -->
                @if($collections->isNotEmpty())
                    <form method="GET" action="{{ route('memory.index') }}" class="flex items-center gap-2">
                        <select name="collection_id" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-medium">
                            @foreach($collections as $col)
                                <option value="{{ $col->id }}" @selected($activeCollection?->id === $col->id)>
                                    {{ $col->name }} ({{ $col->vector_store }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if($activeCollection)
                    @can('memory.manage')
                        <button type="button"
                                onclick="document.getElementById('modal-add-doc').classList.remove('hidden')"
                                class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all">
                            + {{ __('memory.add_note') }}
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        @if($activeCollection)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse($chunks as $chunk)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                        <div>
                            <div class="flex items-start justify-between">
                                <h3 class="font-bold text-slate-900 dark:text-white text-sm line-clamp-1">
                                    {{ $chunk->title }}
                                </h3>
                                @can('memory.manage')
                                    <form method="POST" action="{{ route('memory.chunks.destroy', $chunk) }}" onsubmit="return confirm('Usunąć ten fragment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-500">
                                            &times;
                                        </button>
                                    </form>
                                @endcan
                            </div>
                            <p class="mt-2 text-xs text-slate-600 dark:text-slate-300 line-clamp-4 leading-relaxed font-sans">
                                {{ $chunk->content }}
                            </p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                            <span>ID: {{ substr($chunk->id, 0, 8) }}...</span>
                            <span>{{ $chunk->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                        <p class="text-slate-400 text-sm">
                            Kolekcja [{{ $activeCollection->name }}] nie zawiera jeszcze żadnych dokumentów.
                        </p>
                        @can('memory.manage')
                            <button type="button" onclick="document.getElementById('modal-add-doc').classList.remove('hidden')" class="mt-3 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">
                                + Dodaj pierwszy dokument
                            </button>
                        @endcan
                    </div>
                @endforelse
            </div>

            @if($chunks->hasPages())
                <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    {{ $chunks->links() }}
                </div>
            @endif
        @else
            <div class="p-12 text-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl">
                <p class="text-slate-400 text-sm mb-3">
                    Brak utworzonych kolekcji wektorowych.
                </p>
                <a href="{{ route('memory.collections') }}" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs">
                    + Utwórz kolekcję wektorową
                </a>
            </div>
        @endif
    </div>

    <!-- Modal dodawania dokumentu -->
    @if($activeCollection)
        <div id="modal-add-doc" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                    {{ __('memory.add_note') }}
                </h2>
                <form method="POST" action="{{ route('memory.documents.store', $activeCollection) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('memory.note_title') }}
                        </label>
                        <input type="text" name="title" required placeholder="np. Standardy architektoniczne platformy" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('memory.note_content') }}
                        </label>
                        <textarea name="content" rows="6" required placeholder="Wklej tekst lub Markdown. Dokument zostanie automatycznie podzielony na mniejsze chunki i zindeksowany wektorowo..." class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-sans"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('modal-add-doc').classList.add('hidden')"
                                class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                            Zindeksuj i zapisz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-layouts.app>
