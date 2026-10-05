<x-layouts.app active-module="agents" active-subcategory="templates" :title="__('agents.sub_templates')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('agents.sub_templates') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Gotowe wzorce ról i konfiguracji agentów. Kliknij, aby szybko utworzyć agenta ze wstępnie dobranymi parametrami.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($templates as $tpl)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between hover:border-indigo-500/40 transition-all">
                    <div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase font-mono bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                            {{ $tpl['runtime_type'] }}
                        </span>
                        <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">
                            {{ $tpl['name'] }}
                        </h3>
                        <p class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                            {{ $tpl['description'] }}
                        </p>

                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Model:</span>
                                <span class="font-mono font-medium text-slate-800 dark:text-slate-200">{{ $tpl['primary_model'] }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Temperatura:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200">{{ $tpl['temperature'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <a href="{{ route('agents.create', [
                                'name' => $tpl['name'],
                                'description' => $tpl['description'],
                                'runtime_type' => $tpl['runtime_type'],
                                'primary_model' => $tpl['primary_model'],
                                'temperature' => $tpl['temperature'],
                                'system_prompt' => $tpl['system_prompt'],
                            ]) }}"
                           class="w-full text-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs inline-block shadow-xs transition-all">
                            Użyj tego szablonu
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
