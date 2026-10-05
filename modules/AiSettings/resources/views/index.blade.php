<x-layouts.app active-module="ai-settings" active-subcategory="providers" :title="__('ai.title')">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('ai.sub_providers') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Obsługiwane silniki LLM, domyślne modele oraz stan integracji.
                </p>
            </div>
            <a href="{{ route('ai-settings.accounts') }}"
               class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-2">
                <span>+</span> {{ __('ai.add_account') }}
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($providers as $provider)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-base">
                                    {{ strtoupper(substr($provider->name, 0, 2)) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-white text-base">
                                        {{ $provider->name }}
                                    </h3>
                                    <span class="text-xs text-slate-400 font-mono">
                                        {{ $provider->slug }}
                                    </span>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $provider->is_active ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                {{ $provider->is_active ? 'Aktywny' : 'Wyłączony' }}
                            </span>
                        </div>

                        <div class="mt-4 space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Endpoint:</span>
                                <span class="text-slate-800 dark:text-slate-200 font-mono truncate max-w-[200px]" title="{{ $provider->base_url ?? 'domyślny' }}">
                                    {{ $provider->base_url ?? 'Domyślny chmurowy' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Domyślny model:</span>
                                <span class="text-slate-800 dark:text-slate-200 font-medium">
                                    {{ $provider->default_model ?? 'auto' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-800">
                                <span class="text-slate-500 dark:text-slate-400">Driver:</span>
                                <span class="text-slate-800 dark:text-slate-200 font-mono uppercase font-semibold">
                                    {{ $provider->driver }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-slate-500 dark:text-slate-400">Konta w puli:</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                                    {{ $provider->accounts_count }} kont
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                        <a href="{{ route('ai-settings.accounts') }}?provider={{ $provider->id }}" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                            Zarządzaj kontami &rarr;
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
