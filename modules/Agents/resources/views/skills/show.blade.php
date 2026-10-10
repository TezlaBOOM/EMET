<x-layouts.app active-module="agents" active-subcategory="skills" :title="$skill->name">
    <div class="space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                        {{ $skill->name }}
                    </h1>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        {{ $skill->type }}
                    </span>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">
                        {{ $skill->status }}
                    </span>
                </div>
                <p class="text-sm text-slate-500 font-mono mt-1">slug: {{ $skill->slug }} | v{{ $skill->currentVersion?->version ?? '1.0.0' }}</p>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="testSkillInSandbox()"
                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm rounded-xl shadow-xs">
                    Testuj w sandboxie
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Opis i README -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">Opis i dokumentacja</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">{{ $skill->description ?? 'Brak krótkiego opisu.' }}</p>

                    @if($skill->readme_md)
                        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-sm font-sans prose dark:prose-invert max-w-none whitespace-pre-wrap">
                            {{ $skill->readme_md }}
                        </div>
                    @endif
                </div>

                <!-- Ostatnie wykonania (Runs) -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-4">Historia wywołań w sandboxie</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-300">
                                <tr>
                                    <th class="p-2">Data</th>
                                    <th class="p-2">Status</th>
                                    <th class="p-2">Czas</th>
                                    <th class="p-2">Błąd</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($skill->runs as $run)
                                    <tr class="border-t border-slate-100 dark:border-slate-800">
                                        <td class="p-2 font-mono">{{ $run->created_at?->format('Y-m-d H:i:s') }}</td>
                                        <td class="p-2">
                                            <span class="px-2 py-0.5 rounded-full {{ $run->status === 'success' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                                                {{ $run->status }}
                                            </span>
                                        </td>
                                        <td class="p-2 font-mono">{{ $run->duration_ms }} ms</td>
                                        <td class="p-2 text-rose-500">{{ $run->error ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-slate-400">Brak zarejestrowanych wywołań.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Historia wersji i Wymagania -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">Wymagane zdolności (Requires)</h3>
                    @if(!empty($skill->requires))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($skill->requires as $req)
                                <span class="px-2.5 py-1 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 font-mono text-xs font-semibold">
                                    {{ $req }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400">Brak specjalnych wymagań zdolności.</p>
                    @endif
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white mb-3">Wersje</h3>
                    <div class="space-y-3">
                        @foreach($skill->versions as $v)
                            <div class="p-3 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-xs text-slate-800 dark:text-slate-200">v{{ $v->version }}</div>
                                    <div class="text-xs text-slate-400">{{ $v->changelog ?? 'Brak opisu zmian.' }}</div>
                                </div>
                                @if($v->checksum)
                                    <span class="text-xs font-mono text-slate-400" title="{{ $v->checksum }}">
                                        sha: {{ substr($v->checksum, 0, 8) }}...
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function testSkillInSandbox() {
            fetch('{{ route('agents.skills.test', $skill) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ input: { test: true } })
            })
            .then(res => res.json())
            .then(data => {
                alert('Wynik wykonania w sandboxie: ' + data.status + ' (' + data.duration_ms + ' ms)');
                location.reload();
            })
            .catch(err => alert('Błąd testu: ' + err));
        }
    </script>
</x-layouts.app>
