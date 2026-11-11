<x-layouts.app active-module="chat" active-subcategory="artifacts" :title="__('chat.artifacts_title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('chat.artifacts_title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('chat.artifacts_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @forelse($artifacts as $artifact)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">
                            {{ $artifact->title }}
                        </h3>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-mono uppercase bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                            {{ $artifact->artifact_type }} (v{{ $artifact->version }})
                        </span>
                    </div>
                    <pre class="p-4 rounded-xl bg-slate-950 text-slate-100 text-xs font-mono overflow-x-auto max-h-64">{{ $artifact->content }}</pre>
                </div>
            @empty
                <div class="col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                    Brak wygenerowanych artefaktów w rozmowach. Poproś agenta w czacie o wygenerowanie diagramu Mermaid, widoku HTML lub wykresu SVG.
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>

