<x-layouts.app active-module="system" active-subcategory="first_setup" title="Instrukcja Pierwszej Konfiguracji">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Instrukcja Pierwszej Konfiguracji AgentHub v1.5.0
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Krok po kroku: przewodnik uruchomienia i dostrojenia wszystkich komponentów platformy.
            </p>
        </div>

        <!-- Pasek postępu -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    Postęp konfiguracji: {{ $completedSteps }} z {{ $totalSteps }} kroków ukończonych
                </span>
                <span class="text-sm font-extrabold text-indigo-600 dark:text-indigo-400">
                    {{ $percentage }}%
                </span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-3 overflow-hidden">
                <div class="bg-indigo-600 h-3 rounded-full transition-all duration-500" style="width: {{ $percentage }}%"></div>
            </div>
        </div>

        <!-- Lista kroków -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-2">Etapy wdrożenia platformy</h2>

            @php
                $defaultSteps = [
                    ['key' => 'llm_credentials', 'title' => 'Poświadczenia modeli LLM (OpenAI, Anthropic lub Ollama)', 'module' => 'ai_settings', 'route' => 'ai-settings.index'],
                    ['key' => 'first_agent', 'title' => 'Utworzenie pierwszego Agenta ze zdolnościami internet_mode i context_mode', 'module' => 'agents', 'route' => 'agents.index'],
                    ['key' => 'skills_store', 'title' => 'Import i aktywacja skilli w Magazynie Skilli (sandbox)', 'module' => 'agents', 'route' => 'agents.skills.index'],
                    ['key' => 'group_chat', 'title' => 'Inicjalizacja czatu wieloagentowego z orkiestracją', 'module' => 'chat', 'route' => 'chat.index'],
                    ['key' => 'first_scenario', 'title' => 'Utworzenie pierwszego scenariusza w edytorze Drawflow', 'module' => 'scenarios', 'route' => 'scenarios.index'],
                    ['key' => 'docker_adoption', 'title' => 'Wykrycie i adopcja kontenerów Docker (Hermes/Ollama)', 'module' => 'integrations', 'route' => 'integrations.containers.index'],
                    ['key' => 'config_backup', 'title' => 'Utworzenie pierwszej kopii zapasowej w module transferów', 'module' => 'system', 'route' => 'system.transfers.index'],
                ];
            @endphp

            <div class="space-y-3">
                @foreach($defaultSteps as $step)
                    @php
                        $recorded = $steps->firstWhere('step_key', $step['key']);
                        $isDone = $recorded && $recorded->is_completed;
                    @endphp
                    <div class="flex items-center justify-between p-4 rounded-xl border {{ $isDone ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900' : 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 dark:border-slate-800' }} transition-all">
                        <div class="flex items-center gap-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $isDone ? 'bg-emerald-600 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                @if($isDone) ✓ @else {{ $loop->iteration }} @endif
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white {{ $isDone ? 'line-through text-slate-500 dark:text-slate-400' : '' }}">
                                    {{ $step['title'] }}
                                </h3>
                                <span class="text-xs text-slate-400 capitalize">Moduł: {{ $step['module'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            @if(Route::has($step['route']))
                                <a href="{{ route($step['route']) }}" class="px-3 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-50 dark:hover:bg-indigo-950/50">
                                    Przejdź do modułu →
                                </a>
                            @endif

                            <form method="POST" action="{{ route('system.first-setup.toggle', $step['key']) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 rounded-lg {{ $isDone ? 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300' : 'bg-emerald-600 text-white' }} text-xs font-semibold hover:opacity-90 transition-all">
                                    {{ $isDone ? 'Cofnij' : 'Oznacz jako ukończony' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts.app>
