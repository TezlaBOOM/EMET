<x-layouts.app active-module="agents" active-subcategory="cooperation" :title="__('agents.cooperation.title')">
    <div class="space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm flex items-center justify-between">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm font-medium flex items-center gap-2">
                <span>🔥</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-xs space-y-1">
                @foreach($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <!-- Nagłówek i Pasek Telemetrii Termicznej -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-600 dark:text-indigo-400 text-xs font-semibold mb-2">
                    <span>🤝 Multi-Agent Swarm</span>
                    <span>•</span>
                    <span>🔥 Thermal Overheat Guard</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('agents.cooperation.title') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('agents.cooperation.subtitle') }}
                </p>
            </div>
        </div>

        <!-- Karty KPI Kooperacji i Ochrony Termicznej -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Spięte Zespoły Agentów</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $cooperations->count() }}</span>
                    <span class="text-xs text-indigo-500 font-medium">Dostępnych agentów: {{ $agents->count() }}</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Średnia Temperatura Układu</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold {{ $avgTemperature >= 75 ? 'text-rose-500' : ($avgTemperature >= 50 ? 'text-amber-500' : 'text-emerald-500') }}">
                        {{ number_format($avgTemperature, 1) }}°C
                    </span>
                    <span class="text-xs text-slate-400">Baza spoczynkowa: 22.0°C</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Odcięcia Strażnika Przegrzania</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-amber-500">{{ $overheatTripsCount }}</span>
                    <span class="text-xs text-emerald-500 font-medium">Ochrona pętli aktywna</span>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Wykonane Tury / Tokeny</div>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-2xl font-extrabold text-slate-900 dark:text-white">
                        {{ $cooperations->sum('total_turns_executed') }}
                    </span>
                    <span class="text-xs font-mono text-slate-400">
                        {{ number_format($cooperations->sum('total_tokens_used')) }} tok.
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- LEWA KOLUMNA: Wybór zespołu i Kreator Spinania Agentów (4 kolumny) -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Lista istniejących zespołów kooperacji -->
                @if($cooperations->isNotEmpty())
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-xs space-y-3">
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                            Aktywne Zespoły Kooperacji
                        </h2>
                        <div class="space-y-2">
                            @foreach($cooperations as $coop)
                                @php
                                    $isSelected = $activeCooperation && $activeCooperation->id === $coop->id;
                                    $tempColor = match($coop->thermal_status) {
                                        'overheated' => 'bg-rose-500/15 text-rose-500 border-rose-500/30',
                                        'hot' => 'bg-orange-500/15 text-orange-500 border-orange-500/30',
                                        'warm' => 'bg-amber-500/15 text-amber-500 border-amber-500/30',
                                        default => 'bg-emerald-500/15 text-emerald-500 border-emerald-500/30',
                                    };
                                @endphp
                                <a href="{{ route('agents.cooperation.index', ['cooperation_id' => $coop->id]) }}"
                                   class="block p-3.5 rounded-xl border transition-all {{ $isSelected ? 'border-indigo-500 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                            {{ $coop->name }}
                                        </span>
                                        <span class="px-2 py-0.5 text-[11px] font-mono font-bold rounded-full border {{ $tempColor }}">
                                            {{ number_format($coop->current_temperature, 1) }}°C
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                                        <span>Ogniw: <strong>{{ count($coop->participant_agent_ids ?? []) }} agentów</strong></span>
                                        <span>Topologia: <strong class="uppercase">{{ $coop->topology }}</strong></span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Formularz spinania nowego zespołu agentów -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                        <span>🔗</span>
                        <span>{{ __('agents.cooperation.create_team') }}</span>
                    </h2>

                    <form method="POST" action="{{ route('agents.cooperation.store') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                {{ __('agents.cooperation.team_name') }}
                            </label>
                            <input type="text" name="name" required
                                   placeholder="np. Sztab Architektury, Bezpieczeństwa i Wdrożeń"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                Opis celu zespołu
                            </label>
                            <input type="text" name="description"
                                   placeholder="np. Wspólna debata techniczna, code-review i synteza planu"
                                   class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                    {{ __('agents.cooperation.topology') }}
                                </label>
                                <select name="topology" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                                    <option value="round_table">🔄 Okrągły Stół (Debata)</option>
                                    <option value="pipeline">➡️ Sztafeta (Pipeline A→B→C)</option>
                                    <option value="supervisor">⭐ Gwiazda (Koordynator)</option>
                                    <option value="debate_consensus">⚖️ Kontradyktoryjna (Teza↔Antyteza)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">
                                    {{ __('agents.cooperation.coordinator') }}
                                </label>
                                <select name="coordinator_agent_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                                    <option value="">— Pierwszy z listy —</option>
                                    @foreach($agents as $ag)
                                        <option value="{{ $ag->id }}">{{ $ag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Wybór spiętych agentów -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                                {{ __('agents.cooperation.select_agents') }}
                            </label>
                            <div class="max-h-52 overflow-y-auto space-y-1.5 pr-1">
                                @forelse($agents as $idx => $agentItem)
                                    <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-indigo-400 dark:hover:border-indigo-500/60 cursor-pointer bg-slate-50/60 dark:bg-slate-800/40 transition">
                                        <input type="checkbox" name="participant_agent_ids[]" value="{{ $agentItem->id }}"
                                               {{ $idx < 3 ? 'checked' : '' }}
                                               class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $agentItem->name }}</span>
                                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-200/70 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                    {{ $agentItem->primary_model }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                                Skille: {{ $agentItem->skills->where('is_enabled', true)->pluck('name')->implode(', ') ?: 'Domyślne' }}
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-xs text-amber-600 dark:text-amber-300">
                                        Utwórz najpierw co najmniej 2 agentów w zakładce „Utwórz agenta”.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Konfiguracja Strażnika Przegrzania (Overheat Guard) -->
                        <div class="p-4 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/70 dark:border-rose-900/50 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-rose-700 dark:text-rose-300 flex items-center gap-1.5">
                                    <span>🛡️🔥</span>
                                    <span>{{ __('agents.cooperation.overheat_guard') }}</span>
                                </span>
                                <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 dark:text-rose-300 cursor-pointer">
                                    <input type="checkbox" name="overheat_guard_enabled" value="1" checked
                                           class="rounded border-rose-300 text-rose-600 focus:ring-rose-500">
                                    <span>Aktywny</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        {{ __('agents.cooperation.max_rounds') }}
                                    </label>
                                    <input type="number" name="max_rounds" value="3" min="1" max="20" required
                                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        {{ __('agents.cooperation.max_tokens_budget') }}
                                    </label>
                                    <input type="number" name="max_tokens_budget" value="6000" min="200" max="500000" required
                                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        {{ __('agents.cooperation.max_temperature_score') }}
                                    </label>
                                    <input type="number" step="1" name="max_temperature_score" value="85" min="45" max="100" required
                                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-600 dark:text-slate-400 mb-1">
                                        {{ __('agents.cooperation.repetition_threshold') }}
                                    </label>
                                    <input type="number" step="0.02" name="repetition_threshold" value="0.75" min="0.30" max="0.99" required
                                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2.5 py-1.5 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-300 cursor-pointer pt-1">
                                <input type="checkbox" name="auto_synthesize_on_overheat" value="1" checked
                                       class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span>{{ __('agents.cooperation.auto_synthesize') }}</span>
                            </label>
                        </div>

                        <button type="submit"
                                class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-sm transition-all">
                            🔗 {{ __('agents.cooperation.create_team') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- PRAWA KOLUMNA: Graficzna Wizualizacja Połączeń, Strażnik Przegrzania i Dyskusja (8 kolumn) -->
            <div class="lg:col-span-8 space-y-6">
                @if($activeCooperation)
                    @php
                        $linkedAgents = $activeCooperation->participantAgents();
                        $temp = (float) $activeCooperation->current_temperature;
                        $maxTemp = (float) $activeCooperation->max_temperature_score;
                        $tempPercent = min(100, max(5, round(($temp / 100) * 100)));
                        $cutoffPercent = min(100, max(20, round(($maxTemp / 100) * 100)));
                        $isOverheated = $activeCooperation->thermal_status === 'overheated' || $temp >= $maxTemp;
                    @endphp

                    <!-- 1. GRAFICZNA MAPA SPIĘTYCH AGENTÓW & TERMOMETR PRZEGRZANIA -->
                    <div class="rounded-2xl border {{ $isOverheated ? 'border-rose-500/70 shadow-lg shadow-rose-500/10' : 'border-slate-800' }} bg-slate-950 text-white overflow-hidden">
                        <!-- Pasek tytułowy płótna -->
                        <div class="px-6 py-4 border-b border-slate-800/80 flex flex-wrap items-center justify-between gap-3 bg-slate-900/70">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $isOverheated ? 'bg-rose-500 animate-ping' : 'bg-emerald-400' }}"></span>
                                    <h2 class="text-base font-bold text-white">{{ $activeCooperation->name }}</h2>
                                    <span class="px-2 py-0.5 text-[10px] font-mono uppercase rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                        {{ $activeCooperation->topology }}
                                    </span>
                                </div>
                                @if($activeCooperation->description)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $activeCooperation->description }}</p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('agents.cooperation.cooldown', $activeCooperation) }}">
                                    @csrf
                                    <button type="submit"
                                            title="Czyści przeciągniętą historię czatu, aby agenci nie zaczęli zmyślać, jednocześnie zapisując wszystkie dotychczasowe ustalenia w Pamięci Kontekstowej"
                                            class="px-3 py-1.5 rounded-xl bg-sky-500/20 hover:bg-sky-500/30 text-sky-300 border border-sky-500/30 text-xs font-semibold transition flex items-center gap-1.5">
                                        <span>🔄🧠</span>
                                        <span>{{ __('agents.cooperation.cooldown_btn') }}</span>
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('agents.cooperation.destroy', $activeCooperation) }}"
                                      onsubmit="return confirm('Czy na pewno usunąć ten zespół kooperacji?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="px-2.5 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs transition">
                                        Usuń
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Właściwy Graf Wizualny Połączeń Agentów -->
                        <div class="p-6 relative" style="background-image: radial-gradient(rgba(99, 102, 241, 0.16) 1px, transparent 1px); background-size: 22px 22px;">
                            <!-- Dekoracyjne linie połączeń SVG w tle -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between text-xs text-slate-400 mb-3">
                                    <span>Graficzna Topologia Przepływu Rozmowy i Zadań ({{ $linkedAgents->count() }} spiętych agentów)</span>
                                    <span class="font-mono text-[11px] {{ $isOverheated ? 'text-rose-400 font-bold' : 'text-emerald-400' }}">
                                        Status Termiczny: {{ strtoupper($activeCooperation->thermal_status) }} ({{ number_format($temp, 1) }}°C / limit {{ number_format($maxTemp, 0) }}°C)
                                    </span>
                                </div>

                                <!-- Wizualny Schemat Węzłów Agentów ze Strzałkami Kooperacji -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-stretch relative">
                                    @foreach($linkedAgents as $index => $linkedAgent)
                                        @php
                                            $isCoord = (int) $activeCooperation->coordinator_agent_id === (int) $linkedAgent->id;
                                            $roleBadge = $isCoord ? '👑 Koordynator' : match($index % 3) {
                                                0 => '🧭 Analityk Lead',
                                                1 => '⚙️ Wykonawca Zadania',
                                                default => '🛡️ Audytor & Krytyk',
                                            };
                                        @endphp
                                        <div class="relative rounded-2xl p-4 bg-slate-900/90 border {{ $isCoord ? 'border-indigo-500/70 shadow-md shadow-indigo-500/10' : 'border-slate-800' }} flex flex-col justify-between">
                                            <div>
                                                <div class="flex items-center justify-between gap-2 mb-2">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $isCoord ? 'bg-indigo-500/25 text-indigo-300 border border-indigo-500/40' : 'bg-slate-800 text-slate-300' }}">
                                                        Ogniwo #{{ $index + 1 }} • {{ $roleBadge }}
                                                    </span>
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                </div>

                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                                                        {{ mb_substr($linkedAgent->name, 0, 2) }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="font-bold text-sm text-white truncate">{{ $linkedAgent->name }}</div>
                                                        <div class="text-[11px] font-mono text-indigo-400 truncate">{{ $linkedAgent->primary_model }}</div>
                                                    </div>
                                                </div>

                                                <!-- Przypisane skille agenta -->
                                                <div class="mt-3 flex flex-wrap gap-1">
                                                    @forelse($linkedAgent->skills->where('is_enabled', true)->take(4) as $sk)
                                                        <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                                            ⚡ {{ $sk->name }}
                                                        </span>
                                                    @empty
                                                        <span class="text-[10px] text-slate-500">Wnioskowanie ogólne LLM</span>
                                                    @endforelse
                                                </div>
                                            </div>

                                            <!-- Wskaźnik połączenia do kolejnego agenta -->
                                            @php
                                                $nextLinked = $linkedAgents->get(($index + 1) % $linkedAgents->count());
                                            @endphp
                                            <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
                                                <span>Przekazuje do:</span>
                                                <span class="font-semibold text-indigo-300 flex items-center gap-1">
                                                    <span>➔</span>
                                                    <span>{{ $nextLinked ? $nextLinked->name : 'Synteza' }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- WIZUALNY STRAŻNIK PRZEGRZANIA (THERMAL OVERHEAT GUARD BAR) -->
                            <div class="rounded-2xl bg-slate-900/95 border {{ $isOverheated ? 'border-rose-500' : 'border-slate-800' }} p-4 space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">{{ $isOverheated ? '🚨' : '🌡️' }}</span>
                                        <span class="text-xs font-bold uppercase tracking-wider {{ $isOverheated ? 'text-rose-400' : 'text-slate-200' }}">
                                            Strażnik Przegrzania (Overheat Circuit Breaker):
                                            {{ $activeCooperation->overheat_guard_enabled ? 'AKTYWNY' : 'WYŁĄCZONY' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs font-mono">
                                        <span>Temperatura: <strong class="{{ $isOverheated ? 'text-rose-400' : 'text-emerald-400' }}">{{ number_format($temp, 1) }}°C</strong></span>
                                        <span>Próg odcięcia: <strong class="text-rose-400">{{ number_format($maxTemp, 0) }}°C</strong></span>
                                        <span>Rundy max: <strong>{{ $activeCooperation->max_rounds }}</strong></span>
                                        <span>Budżet: <strong>{{ number_format($activeCooperation->max_tokens_budget) }} tok.</strong></span>
                                    </div>
                                </div>

                                <!-- Graficzny Pasek Termometru z Linią Odcięcia -->
                                <div class="relative h-4 w-full rounded-full bg-slate-800 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500 {{ $isOverheated ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-rose-600' : 'bg-gradient-to-r from-emerald-500 via-teal-400 to-amber-500' }}"
                                         style="width: {{ $tempPercent }}%;"></div>
                                    <!-- Linia progu odcięcia -->
                                    <div class="absolute top-0 bottom-0 w-0.5 bg-rose-400"
                                         style="left: {{ $cutoffPercent }}%;"
                                         title="Próg odcięcia przegrzania: {{ $maxTemp }}°C"></div>
                                </div>

                                <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                    <span>20°C (Spoczynek)</span>
                                    <span>50°C (Praca Zespołowa)</span>
                                    <span class="text-rose-400">⚡ {{ number_format($maxTemp, 0) }}°C (Odcięcie Overheat Guard)</span>
                                    <span>100°C</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. FORMULARZ URUCHOMIENIA DYSKUSJI I ZADANIA DLA SPIĘTYCH AGENTÓW -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                            <span>💬⚡</span>
                            <span>Zleć zadanie do wspólnej dyskusji i wykonania przez zespół „{{ $activeCooperation->name }}”</span>
                        </h3>

                        <form method="POST" action="{{ route('agents.cooperation.run', $activeCooperation) }}" class="space-y-4">
                            @csrf
                            <div>
                                <textarea name="task_prompt" rows="3" required
                                          placeholder="Opisz problem lub zadanie dla zespołu spiętych agentów (np. Zaprojektuj architekturę bezpiecznego pipeline'u RAG, wykryj potencjalne luki i przygotuj plan wdrożenia)..."
                                          class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/90 px-4 py-3 text-sm text-slate-900 dark:text-white">{{ $activeCooperation->last_task_prompt ?? 'Zaprojektuj odporną architekturę wdrożenia lokalnych agentów AI z weryfikacją jakości, podziel obowiązki w zespole i przygotuj listę kontrolną bezpieczeństwa.' }}</textarea>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                                        Liczba rund debaty (opcjonalnie):
                                    </label>
                                    <input type="number" name="rounds_override" min="1" max="25"
                                           value="{{ $activeCooperation->max_rounds }}"
                                           class="w-20 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-mono text-slate-900 dark:text-white">
                                    <span class="text-[11px] text-slate-400">
                                        (Wpisz wartość większą niż {{ $activeCooperation->max_rounds }}, aby przetestować zadziałanie bezpiecznika przegrzania)
                                    </span>
                                </div>

                                <button type="submit"
                                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm transition-all flex items-center gap-2">
                                    <span>▶</span>
                                    <span>{{ __('agents.cooperation.run_task_btn') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 3. ZAPIS DYSKUSJI AGENTÓW, ALERTY PRZEGRZANIA I SYNTEZA KOŃCOWA -->
                    @php
                        $discussionLog = $activeCooperation->discussion_log ?? [];
                        $overheatEvents = $activeCooperation->overheat_events ?? [];
                    @endphp

                    @if(!empty($overheatEvents))
                        @php $lastEvent = end($overheatEvents); @endphp
                        <div class="p-5 rounded-2xl bg-rose-950/90 border border-rose-500 text-rose-100 space-y-1.5 shadow-md">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-rose-300 flex items-center gap-2">
                                    <span>🔥🛡️ ZADZIAŁAŁ STRAŻNIK PRZEGRZANIA (OVERHEAT CIRCUIT BREAKER)</span>
                                </span>
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-rose-500/30 text-rose-200">
                                    {{ $lastEvent['reason'] ?? 'overheat' }} • {{ $lastEvent['temperature'] ?? $activeCooperation->current_temperature }}°C
                                </span>
                            </div>
                            <p class="text-xs text-rose-200">
                                {{ $lastEvent['message'] ?? 'Przekroczono bezpieczne parametry pracy zespołu. Dalsza pętla została zatrzymana.' }}
                            </p>
                        </div>
                    @endif

                    @if($activeCooperation->preserved_context)
                        <div class="bg-emerald-950/90 border border-emerald-500/40 rounded-2xl p-5 text-emerald-100 shadow-sm space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-emerald-300 flex items-center gap-2">
                                    <span>🧠 Zachowany Kontekst Rozmowy po Przeładowaniu Czatu (Pamięć Anty-Halucynacyjna)</span>
                                </span>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-200 text-[11px] font-mono">
                                    Przeładowania czatu: #{{ $activeCooperation->context_reloads_count }}
                                </span>
                            </div>
                            <p class="text-[11px] text-emerald-300/80">
                                Ten skondensowany kontekst jest automatycznie wstrzykiwany do każdego agenta po przeładowaniu czatu — dzięki temu agenci pamiętają wszystkie ustalenia, ale nie zmyślają od nadmiaru historii.
                            </p>
                            <pre class="whitespace-pre-wrap text-xs leading-relaxed text-emerald-100 font-sans bg-slate-950/60 p-3.5 rounded-xl border border-emerald-500/20">{{ $activeCooperation->preserved_context }}</pre>
                        </div>
                    @endif

                    @if($activeCooperation->final_synthesis)
                        <div class="bg-gradient-to-br from-indigo-950/90 via-slate-900 to-slate-900 border border-indigo-500/40 rounded-2xl p-6 text-white shadow-sm space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-300">
                                    🎯 Konsensus i Wynik Końcowy Zespołu Kooperacji
                                </span>
                                <span class="text-[11px] font-mono text-slate-400">
                                    Tury: {{ count($discussionLog) }}
                                </span>
                            </div>
                            <pre class="whitespace-pre-wrap text-xs leading-relaxed text-slate-200 font-sans">{{ $activeCooperation->final_synthesis }}</pre>
                        </div>
                    @endif

                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                                Przebieg Dyskusji i Przekazywania Zadań Pomiędzy Agentami ({{ count($discussionLog) }} wypowiedzi)
                            </h3>
                        </div>

                        @if(!empty($discussionLog))
                            <div class="space-y-3">
                                @foreach($discussionLog as $entry)
                                    <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 space-y-2">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded-md bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[11px] font-mono font-bold">
                                                    Runda {{ $entry['round'] }} • Tura #{{ $entry['turn'] }}
                                                </span>
                                                <span class="font-bold text-sm text-slate-900 dark:text-white">
                                                    {{ $entry['agent_name'] }}
                                                </span>
                                                <span class="text-xs text-slate-400">({{ $entry['role'] }})</span>
                                                <span class="text-xs font-semibold text-indigo-500">
                                                    ➔ {{ $entry['target_agent_name'] ?? 'Zespół' }}
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-2 text-[11px] font-mono">
                                                <span class="px-2 py-0.5 rounded bg-slate-200/70 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                    {{ $entry['tokens'] }} tok.
                                                </span>
                                                <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400">
                                                    +{{ $entry['temperature_delta'] }}°C ➔ {{ $entry['temperature_after'] }}°C
                                                </span>
                                                <span class="px-2 py-0.5 rounded bg-slate-200/70 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                                    Loop: {{ number_format(($entry['loop_similarity'] ?? 0) * 100, 0) }}%
                                                </span>
                                            </div>
                                        </div>

                                        <p class="text-xs leading-relaxed text-slate-700 dark:text-slate-200">
                                            {{ $entry['content'] }}
                                        </p>

                                        @if(!empty($entry['skills_used']))
                                            <div class="flex flex-wrap gap-1 pt-1">
                                                @foreach($entry['skills_used'] as $skillName)
                                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                                        ⚡ {{ $skillName }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center text-sm text-slate-400">
                                Zespół agentów jest spięty i gotowy do pracy. Kliknij <strong>„Uruchom dyskusję i zadanie”</strong> powyżej, aby rozpocząć wymianę zdań.
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-12 text-center space-y-3">
                        <div class="text-4xl">🤝🔥</div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                            Brak spiętego zespołu kooperacji agentów
                        </h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-lg mx-auto">
                            Skorzystaj z kreatora po lewej stronie, aby wybrać kilku agentów AI, połączyć ich w topologię dyskusji i skonfigurować Strażnika Przegrzania (Overheat Guard).
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>

