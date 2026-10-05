<x-layouts.app active-module="ai-settings" active-subcategory="accounts" :title="__('ai.title')">
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

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('ai.sub_accounts') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Wszystkie klucze API przechowywane są w bazie w formie zaszyfrowanej (AES-256-CBC).
                </p>
            </div>
            <button type="button"
                    onclick="document.getElementById('modal-add-account').classList.remove('hidden')"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                + {{ __('ai.add_account') }}
            </button>
        </div>

        <!-- Tabela kont -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('ai.account_name') }}</th>
                            <th class="px-6 py-3.5">{{ __('ai.provider') }}</th>
                            <th class="px-6 py-3.5">{{ __('ai.weight') }}</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5">Ostatni test</th>
                            <th class="px-6 py-3.5 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($accounts as $account)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors" id="row-account-{{ $account->id }}">
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $account->name }}</span>
                                        @if($account->organization_id)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500 font-mono">
                                                {{ $account->organization_id }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $account->provider->name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs">
                                    {{ $account->weight }}
                                </td>
                                <td class="px-6 py-4">
                                    <span id="status-badge-{{ $account->id }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($account->current_status === 'active') bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800
                                        @elseif($account->current_status === 'cooldown') bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800
                                        @else bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 @endif">
                                        {{ $account->current_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500 font-mono" id="last-tested-{{ $account->id }}">
                                    {{ $account->last_tested_at ? $account->last_tested_at->diffForHumans() : 'Nigdy' }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button type="button"
                                            onclick="testConnection({{ $account->id }})"
                                            id="btn-test-{{ $account->id }}"
                                            class="px-2.5 py-1 text-xs font-medium rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition-colors">
                                        {{ __('ai.test_connection') }}
                                    </button>
                                    <form method="POST" action="{{ route('ai-settings.accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Usunąć to konto?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline text-xs font-medium">
                                            Usuń
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-400 text-sm">
                                    Brak skonfigurowanych kont. Kliknij "+ {{ __('ai.add_account') }}", aby dodać pierwsze konto API.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Dodaj konto -->
    <div id="modal-add-account" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                {{ __('ai.add_account') }}
            </h2>
            <form method="POST" action="{{ route('ai-settings.accounts.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('ai.provider') }}
                    </label>
                    <select name="provider_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        @foreach($providers as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('ai.account_name') }}
                    </label>
                    <input type="text" name="name" placeholder="np. Gemini Flash Pro #1" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('ai.api_key') }}
                    </label>
                    <input type="password" name="api_key" placeholder="{{ __('ai.api_key_placeholder') }}" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">Klucz zostanie zaszyfrowany w locie za pomocą klucza aplikacji (AES-256).</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('ai.organization_id') }}
                    </label>
                    <input type="text" name="organization_id" placeholder="np. org_12345" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('ai.weight') }}
                        </label>
                        <input type="number" name="weight" value="10" min="1" max="100" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('ai.rpm_limit') }}
                        </label>
                        <input type="number" name="rpm_limit" placeholder="60" min="1" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('ai.tpm_limit') }}
                        </label>
                        <input type="number" name="tpm_limit" placeholder="100000" min="1" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-add-account').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                        {{ __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function testConnection(accountId) {
            const btn = document.getElementById('btn-test-' + accountId);
            const statusBadge = document.getElementById('status-badge-' + accountId);
            const lastTested = document.getElementById('last-tested-' + accountId);

            btn.disabled = true;
            btn.innerText = 'Testowanie...';

            fetch(`/ai-settings/accounts/${accountId}/test`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = '{{ __('ai.test_connection') }}';
                if (data.success) {
                    statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
                    statusBadge.innerText = 'active';
                    lastTested.innerText = `${data.latency_ms} ms (sukces)`;
                    alert(`Połączenie udane! Czas odpowiedzi: ${data.latency_ms} ms.`);
                } else {
                    statusBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800';
                    statusBadge.innerText = 'error';
                    lastTested.innerText = 'błąd';
                    alert(`Błąd połączenia: ${data.message}`);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = '{{ __('ai.test_connection') }}';
                alert('Błąd wywołania testu: ' + err.message);
            });
        }
    </script>
</x-layouts.app>
