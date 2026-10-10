<x-layouts.app active-module="system" active-subcategory="transfers" title="Eksport i Import Konfiguracji">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Eksport i Import Konfiguracji (v1.5.0)
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zarządzanie transferami konfiguracji platformy AgentHub z obsługą szyfrowania Argon2id + libsodium i raportu dry-run.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Karta Eksportu -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Eksport konfiguracji</h2>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Pobierz kompletną paczkę konfiguracji (wszystkie sekcje, definicje agentów, paczki skilli i scenariusze) do archiwum ZIP.
                </p>

                <form method="POST" action="{{ route('system.transfers.export') }}" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" name="include_secrets" value="1" id="export_include_secrets"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span>Dołącz zaszyfrowane sekrety (klucze API, hasła integracji)</span>
                        </label>
                    </div>

                    <div id="export_password_box" class="space-y-1">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Hasło szyfrowania sekretów (Argon2id + XChaCha20)
                        </label>
                        <input type="password" name="password" placeholder="Wprowadź silne hasło..."
                               class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2">
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all">
                        Wygeneruj i pobierz paczkę ZIP
                    </button>
                </form>
            </div>

            <!-- Karta Importu -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-2">
                    <span class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12" />
                        </svg>
                    </span>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Import konfiguracji</h2>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Wgraj archiwum ZIP wygenerowane przez AgentHub. Przed zaaplikowaniem zmian automatycznie tworzony jest backup.
                </p>

                <form method="POST" action="{{ route('system.transfers.import') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Plik paczki ZIP
                        </label>
                        <input type="file" name="file" accept=".zip" required
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Tryb łączenia (mode)
                            </label>
                            <select name="mode" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2">
                                <option value="merge">Scal (merge - domyślny)</option>
                                <option value="overwrite">Nadpisz (overwrite)</option>
                                <option value="new_only">Tylko nowe (new_only)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Hasło do sekretów
                            </label>
                            <input type="password" name="password" placeholder="Opcjonalne hasło..."
                                   class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2">
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" formaction="{{ route('system.transfers.dry-run') }}" class="flex-1 py-2.5 px-4 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">
                            Podgląd Dry-Run (Diff)
                        </button>
                        <button type="submit" class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md shadow-emerald-600/20 transition-all">
                            Zaaplikuj import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Historia transferów -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Historia transferów konfiguracji</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 font-semibold border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Typ</th>
                            <th class="px-4 py-3">Plik / Hash</th>
                            <th class="px-4 py-3">Tryb</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Sekrety</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($transfers as $t)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3 text-slate-500 font-mono text-[11px]">{{ $t->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 font-semibold {{ $t->type === 'export' ? 'text-indigo-600' : 'text-emerald-600' }}">
                                    {{ strtoupper($t->type) }}
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px]">
                                    <div>{{ $t->file_name }}</div>
                                    @if($t->file_hash)
                                        <div class="text-[10px] text-slate-400">SHA: {{ Str::limit($t->file_hash, 16) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $t->mode }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $t->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                                        {{ $t->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $t->has_secrets ? 'Zaszyfrowane' : 'Brak' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    Brak zarejestrowanych transferów konfiguracji.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
