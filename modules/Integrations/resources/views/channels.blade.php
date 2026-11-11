<x-layouts.app active-module="integrations" active-subcategory="channels" :title="__('integrations.channels.title')">
    <div class="space-y-6">
        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('integrations.channels.title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('integrations.channels.subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">
                    {{ __('integrations.channels.create') }}
                </h2>
                <form method="POST" action="{{ route('integrations.channels.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nazwa kanału / bota</label>
                        <input type="text" name="name" required placeholder="np. Slack DevOps Approvals"
                               class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Platforma</label>
                        <select name="platform" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="slack">Slack (Block Kit Interactive)</option>
                            <option value="telegram">Telegram (Bot API InlineKeyboard)</option>
                            <option value="discord">Discord (Webhook & Components)</option>
                            <option value="teams">Microsoft Teams (Adaptive Cards)</option>
                            <option value="email">IMAP / SMTP Gateway</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Przypisany Agent</label>
                        <select name="agent_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="">— Brak —</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Przypisany Scenariusz</label>
                        <select name="scenario_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="">— Brak —</option>
                            @foreach($scenarios as $scenario)
                                <option value="{{ $scenario->id }}">{{ $scenario->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm">
                        {{ __('integrations.channels.create') }}
                    </button>
                </form>
            </div>

            <div class="lg:col-span-2 space-y-4">
                @forelse($channels as $ch)
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-mono uppercase bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-bold">
                                    {{ $ch->platform }}
                                </span>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $ch->name }}</h3>
                            </div>
                            <p class="text-xs text-slate-500 font-mono mt-1">
                                Webhook: POST /api/v1/hooks/channels/{{ $ch->slug }} | Obsłużono zdarzeń: {{ $ch->messages_handled_count }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-8 text-center text-sm text-slate-400">
                        Brak skonfigurowanych kanałów zewnętrznych ChatOps.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>

