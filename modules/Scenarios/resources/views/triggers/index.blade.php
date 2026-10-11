<x-layouts.app active-module="scenarios" active-subcategory="all" :title="'Harmonogramy: ' . $scenario->name">
    <div class="space-y-6 max-w-6xl mx-auto" x-data="{
        showModal: false,
        type: 'daily',
        runAt: '',
        intervalMinutes: 60,
        time: '09:00',
        days: ['monday'],
        dayOfMonth: 1,
        cronExpr: '0 * * * *',
        timezone: 'Europe/Warsaw',
        previews: [],
        loadingPreview: false,
        fetchPreview() {
            this.loadingPreview = true;
            let schedule = {};
            if (this.type === 'once') schedule = { run_at: this.runAt };
            else if (this.type === 'interval') schedule = { interval_minutes: parseInt(this.intervalMinutes) || 60 };
            else if (this.type === 'daily') schedule = { time: this.time };
            else if (this.type === 'weekly') schedule = { days: this.days, time: this.time };
            else if (this.type === 'monthly') schedule = { day_of_month: parseInt(this.dayOfMonth) || 1, time: this.time };
            else if (this.type === 'cron') schedule = { cron_expression: this.cronExpr };

            fetch('{{ route('scenarios.triggers.preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    schedule_type: this.type,
                    schedule: schedule,
                    timezone: this.timezone
                })
            })
            .then(res => res.json())
            .then(data => {
                this.previews = data.runs || [];
                this.loadingPreview = false;
            })
            .catch(() => {
                this.loadingPreview = false;
            });
        }
    }">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('scenarios.index') }}" class="hover:underline">Scenariusze</a>
                    <span>/</span>
                    <a href="{{ route('scenarios.edit', $scenario) }}" class="hover:underline font-mono">{{ $scenario->slug }}</a>
                    <span>/</span>
                    <span>Harmonogramy & Timery</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Harmonogramy scenariusza: {{ $scenario->name }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Automatyczne wyzwalanie scenariusza w zadanym czasie w strefie Europe/Warsaw z ochroną przed współbieżnością.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('scenarios.edit', $scenario) }}"
                   class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 text-sm font-medium transition-all">
                    &larr; Wróć do edycji
                </a>
                <button type="button" @click="showModal = true; fetchPreview()"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span>Nowy harmonogram</span>
                </button>
            </div>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm flex items-center justify-between">
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Lista harmonogramów --}}
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                    Zdefiniowane wyzwalacze czasowe ({{ $triggers->count() }})
                </h3>
                <span class="text-xs text-slate-400 font-mono">Domyślna strefa: Europe/Warsaw</span>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($triggers as $trigger)
                    <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all">
                        <div class="space-y-1.5 flex-1">
                            <div class="flex items-center gap-2.5">
                                <h4 class="font-bold text-slate-900 dark:text-white text-base">
                                    {{ $trigger->name }}
                                </h4>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider
                                    @if($trigger->state === 'active') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300
                                    @elseif($trigger->state === 'paused') bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300
                                    @elseif($trigger->state === 'error') bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300
                                    @else bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 @endif">
                                    {{ $trigger->state }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-mono">
                                    Typ: {{ $trigger->schedule_type }}
                                </span>
                            </div>

                            <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-4 gap-y-1">
                                <div>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">Następne uruchomienie:</span>
                                    <span class="font-mono text-indigo-600 dark:text-indigo-400 font-semibold">
                                        {{ $trigger->next_run_at ? $trigger->next_run_at->format('Y-m-d H:i:s') : 'Brak (nieaktywny/zakończony)' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">Ostatnie uruchomienie:</span>
                                    <span class="font-mono">
                                        {{ $trigger->last_run_at ? $trigger->last_run_at->format('Y-m-d H:i:s') : 'Nigdy' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">Wykonania:</span>
                                    <span class="font-mono">{{ $trigger->runs_count }} @if($trigger->max_runs) / {{ $trigger->max_runs }} @endif</span>
                                </div>
                            </div>

                            @if($trigger->consecutive_failures > 0)
                                <div class="mt-2 p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 text-xs text-rose-700 dark:text-rose-300">
                                    <span class="font-bold">Awaria ({{ $trigger->consecutive_failures }}/5):</span>
                                    {{ $trigger->last_error }}
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 self-start md:self-center">
                            {{-- Uruchom teraz --}}
                            <form method="POST" action="{{ route('scenarios.triggers.run-now', [$scenario, $trigger]) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 hover:bg-indigo-100 dark:hover:bg-indigo-900 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-semibold transition-all flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Uruchom teraz</span>
                                </button>
                            </form>

                            {{-- Przełącz stan (Włącz / Wstrzymaj) --}}
                            <form method="POST" action="{{ route('scenarios.triggers.toggle', [$scenario, $trigger]) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all
                                        {{ $trigger->enabled ? 'border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 hover:bg-amber-100' : 'border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100' }}">
                                    {{ $trigger->enabled ? 'Wstrzymaj' : 'Wznów' }}
                                </button>
                            </form>

                            {{-- Usuń --}}
                            <form method="POST" action="{{ route('scenarios.triggers.destroy', [$scenario, $trigger]) }}" onsubmit="return confirm('Czy na pewno chcesz usunąć ten harmonogram?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="px-2.5 py-1.5 rounded-lg border border-rose-200 dark:border-rose-900/50 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-semibold transition-all">
                                    Usuń
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="p-12 text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="font-medium text-slate-600 dark:text-slate-300 text-sm">Brak skonfigurowanych harmonogramów dla tego scenariusza.</p>
                        <p class="text-xs text-slate-400 mt-1">Dodaj wyzwalacz czasowy, aby scenariusz uruchamiał się automatycznie.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Modal tworzenia nowego harmonogramu --}}
        <div x-show="showModal"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             @keydown.escape.window="showModal = false">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl overflow-hidden shadow-2xl"
                 @click.outside="showModal = false">
                <form method="POST" action="{{ route('scenarios.triggers.store', $scenario) }}" class="p-6 space-y-5">
                    @csrf
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                            Nowy wyzwalacz czasowy
                        </h3>
                        <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Nazwa harmonogramu
                            </label>
                            <input type="text" name="name" required placeholder="np. Poranny raport codzienny"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    Typ harmonogramu
                                </label>
                                <select name="schedule_type" x-model="type" @change="fetchPreview()"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="daily">Codziennie (daily)</option>
                                    <option value="interval">Interwał minutowy (interval)</option>
                                    <option value="weekly">Cotygodniowo (weekly)</option>
                                    <option value="monthly">Comiesięcznie (monthly)</option>
                                    <option value="once">Jednorazowo (once)</option>
                                    <option value="cron">Własne wyrażenie Cron (cron)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    Strefa czasowa
                                </label>
                                <select name="timezone" x-model="timezone" @change="fetchPreview()"
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500">
                                    <option value="Europe/Warsaw">Europe/Warsaw (domyślna)</option>
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="Europe/London">Europe/London</option>
                                </select>
                            </div>
                        </div>

                        {{-- Warunkowe pola w zależności od typu --}}
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 space-y-3">
                            <template x-if="type === 'once'">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Data i godzina wykonania (run_at)</label>
                                    <input type="datetime-local" name="run_at" x-model="runAt" @input="fetchPreview()"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                </div>
                            </template>

                            <template x-if="type === 'interval'">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Co ile minut (interval_minutes)</label>
                                    <input type="number" name="interval_minutes" min="1" x-model="intervalMinutes" @input="fetchPreview()"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                </div>
                            </template>

                            <template x-if="type === 'daily'">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Godzina wykonania (HH:MM)</label>
                                    <input type="time" name="time" x-model="time" @input="fetchPreview()"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                </div>
                            </template>

                            <template x-if="type === 'weekly'">
                                <div class="space-y-2">
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">Dni tygodnia</label>
                                    <div class="grid grid-cols-4 gap-2 text-xs">
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="monday" checked @change="fetchPreview()"> Poniedziałek</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="tuesday" @change="fetchPreview()"> Wtorek</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="wednesday" @change="fetchPreview()"> Środa</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="thursday" @change="fetchPreview()"> Czwartek</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="friday" @change="fetchPreview()"> Piątek</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="saturday" @change="fetchPreview()"> Sobota</label>
                                        <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="sunday" @change="fetchPreview()"> Niedziela</label>
                                    </div>
                                    <div class="pt-2">
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Godzina wykonania (HH:MM)</label>
                                        <input type="time" name="time" x-model="time" @input="fetchPreview()"
                                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                    </div>
                                </div>
                            </template>

                            <template x-if="type === 'monthly'">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Dzień miesiąca (1-31)</label>
                                        <input type="number" name="day_of_month" min="1" max="31" x-model="dayOfMonth" @input="fetchPreview()"
                                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Godzina wykonania (HH:MM)</label>
                                        <input type="time" name="time" x-model="time" @input="fetchPreview()"
                                               class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                                    </div>
                                </div>
                            </template>

                            <template x-if="type === 'cron'">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Wyrażenie Cron (np. */15 * * * *)</label>
                                    <input type="text" name="cron_expression" x-model="cronExpr" @input="fetchPreview()"
                                           class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-mono" />
                                </div>
                            </template>
                        </div>

                        {{-- Podgląd 5 kolejnych uruchomień --}}
                        <div class="p-3.5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 block mb-1.5">
                                Podgląd najbliższych 5 uruchomień:
                            </span>
                            <div class="space-y-1">
                                <template x-if="loadingPreview">
                                    <p class="text-xs text-slate-400">Przeliczanie terminów...</p>
                                </template>
                                <template x-for="(run, index) in previews" :key="index">
                                    <div class="text-xs font-mono text-indigo-950 dark:text-indigo-200 flex items-center gap-1.5">
                                        <span class="text-indigo-400" x-text="(index + 1) + '.'"></span>
                                        <span x-text="run"></span>
                                    </div>
                                </template>
                                <template x-if="!loadingPreview && previews.length === 0">
                                    <p class="text-xs text-slate-400">Brak kolejnych zaplanowanych uruchomień dla zadanych parametrów.</p>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                Maksymalna liczba wykonań (opcjonalne)
                            </label>
                            <input type="number" name="max_runs" min="1" placeholder="Pozostaw puste dla nielimitowanych"
                                   class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="showModal = false"
                                class="px-4 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800">
                            Anuluj
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md transition-colors">
                            Zapisz harmonogram
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
