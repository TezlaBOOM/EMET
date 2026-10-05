<x-layouts.app active-module="dashboard" :title="__('setup.title')">
    <div class="max-w-3xl mx-auto py-8 space-y-6">

        <!-- Karta nagłówkowa kreatora -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                        PE
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('setup.title') }}</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('setup.subtitle') }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400">
                        {{ __('setup.step_counter', ['current' => $step, 'total' => $totalSteps]) }}
                    </span>
                </div>
            </div>

            <!-- Pasek postępu kroków -->
            <div class="grid grid-cols-7 gap-2 mt-6">
                @for($i = 1; $i <= 7; $i++)
                    <div class="h-2 rounded-full transition-all duration-300 {{ $i < $step ? 'bg-indigo-600' : ($i === $step ? 'bg-indigo-500 ring-2 ring-indigo-400/30' : 'bg-slate-100 dark:bg-slate-800') }}"></div>
                @endfor
            </div>
        </div>

        <!-- Formularz danego kroku -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 shadow-xs">
            <form action="{{ route('setup.save', ['step' => $step]) }}" method="POST" class="space-y-6">
                @csrf

                @if($step === 1)
                    <!-- Krok 1: Powitanie i preferencje -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step1_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step1_desc') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('setup.language') }}</label>
                            <select name="locale" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="pl" {{ app()->getLocale() === 'pl' ? 'selected' : '' }}>Polski (Domyślny)</option>
                                <option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('setup.theme') }}</label>
                            <select name="theme" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm focus:ring-2 focus:ring-indigo-500">
                                <option value="dark">{{ __('setup.theme_dark') }}</option>
                                <option value="light">{{ __('setup.theme_light') }}</option>
                            </select>
                        </div>
                    </div>

                @elseif($step === 2)
                    <!-- Krok 2: Dane konta administratora -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step2_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step2_desc') }}</p>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.admin_email') }}</label>
                            <input type="email" name="email" value="{{ auth()->user()->email ?? 'admin@admin.lan' }}" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.admin_password') }}</label>
                                <input type="password" name="password" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm" placeholder="••••••••">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.admin_password_confirmation') }}</label>
                                <input type="password" name="password_confirmation" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                @elseif($step === 3)
                    <!-- Krok 3: Silnik wektorowy -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step3_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step3_desc') }}</p>
                    </div>

                    <div class="space-y-3 pt-2">
                        <label class="flex items-start gap-3 p-4 rounded-xl border border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/30 dark:bg-indigo-950/20 cursor-pointer">
                            <input type="radio" name="vector_driver" value="qdrant" checked class="mt-1 text-indigo-600">
                            <div>
                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('setup.vector_qdrant') }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">Domyślny port 6333, wsparcie gRPC, zaawansowane indeksowanie payloadu HNSW.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-850 cursor-pointer">
                            <input type="radio" name="vector_driver" value="pgvector" class="mt-1 text-indigo-600">
                            <div>
                                <div class="text-sm font-bold text-slate-900 dark:text-white">{{ __('setup.vector_pgvector') }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">Współdzielona baza danych z tabelami relacyjnymi PostgreSQL.</div>
                            </div>
                        </label>
                    </div>

                @elseif($step === 4)
                    <!-- Krok 4: Dostawca AI -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step4_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step4_desc') }}</p>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.provider') }}</label>
                            <select name="provider_id" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm">
                                @foreach($providers as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->driver }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.account_name') }}</label>
                            <input type="text" name="account_name" value="Konto Główne" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.api_key') }}</label>
                            <input type="password" name="api_key" placeholder="sk-..." class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm">
                        </div>
                    </div>

                @elseif($step === 5)
                    <!-- Krok 5: Detekcja runtime -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step5_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step5_desc') }}</p>
                    </div>

                    <div class="space-y-3 pt-2">
                        @foreach($detections as $name => $det)
                            <div class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-2.5 h-2.5 rounded-full {{ $det['installed'] ? 'bg-emerald-500' : 'bg-slate-400' }}"></div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-900 dark:text-white uppercase">{{ $name }}</div>
                                        <div class="text-xs text-slate-500">{{ $det['version'] ?? 'Nie wykryto' }} &bull; {{ $det['binary_path'] ?? '-' }}</div>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded text-2xs font-semibold {{ $det['installed'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $det['installed'] ? 'Zainstalowany (Mock/Active)' : 'Dostępny w chmurze' }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                @elseif($step === 6)
                    <!-- Krok 6: Pierwszy agent -->
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('setup.step6_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ __('setup.step6_desc') }}</p>
                    </div>

                    <div class="space-y-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('setup.agent_name') }}</label>
                            <input type="text" name="agent_name" value="Główny Asystent" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white p-3 text-sm">
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="enable_memory" checked class="rounded text-indigo-600">
                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ __('setup.enable_memory') }}</span>
                        </label>
                    </div>

                @elseif($step === 7)
                    <!-- Krok 7: Podsumowanie -->
                    <div class="text-center py-6 space-y-4">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto text-3xl">
                            ✓
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('setup.step7_title') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                            {{ __('setup.ready_msg') }}
                        </p>
                    </div>
                @endif

                <!-- Pasek akcji nawigacji -->
                <div class="flex items-center justify-between pt-6 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        @if($step > 1)
                            <a href="{{ route('setup.step', ['step' => $step - 1]) }}" class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900">
                                &larr; {{ __('setup.prev_step') }}
                            </a>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        @if($step < 7)
                            <button type="submit" formaction="{{ route('setup.skip', ['step' => $step]) }}" formmethod="POST" class="px-4 py-2 rounded-xl text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition">
                                {{ __('setup.skip_step') }}
                            </button>
                            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm shadow-sm transition">
                                {{ __('setup.next_step') }} &rarr;
                            </button>
                        @else
                            <button type="submit" class="px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm shadow-sm transition">
                                {{ __('setup.finish_wizard') }}
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>

    </div>
</x-layouts.app>
