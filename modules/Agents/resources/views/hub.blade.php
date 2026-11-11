<x-layouts.app active-module="agents" active-subcategory="hub" :title="__('agents.hub.title')">
    <div class="space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('agents.hub.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('agents.hub.subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                    {{ __('agents.hub.add_registry') }}
                </h2>
                <form method="POST" action="{{ route('agents.hub.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nazwa katalogu</label>
                        <input type="text" name="name" required placeholder="np. Projekt-Emet Official Hub"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Adres URL katalogu</label>
                        <input type="url" name="registry_url" required placeholder="https://hub.agenthub.local/v1/catalog.json"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Klucz publiczny Ed25519 (HEX, opcjonalnie)</label>
                        <input type="text" name="public_key_ed25519" placeholder="Pozostaw puste, aby wygenerować nową parę libsodium"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm">
                        {{ __('agents.hub.add_registry') }}
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-4">
                @forelse($registries as $reg)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $reg->name }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                                Ed25519 Verified
                            </span>
                        </div>
                        <p class="text-xs font-mono text-slate-500">{{ $reg->registry_url }}</p>
                        <p class="text-[11px] font-mono text-slate-400 truncate">PubKey: {{ $reg->public_key_ed25519 }}</p>
                    </div>
                @empty
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                        Brak podłączonych rejestrów sfederowanych Ed25519.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>

