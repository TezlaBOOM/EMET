<x-layouts.app active-module="chat" active-subcategory="history" :title="__('chat.history')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('chat.history') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Archiwum przeprowadzonych rozmów ze wszystkimi agentami.
                </p>
            </div>
            <a href="{{ route('chat.index') }}"
               class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                + {{ __('chat.new_conversation') }}
            </a>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">Agent</th>
                            <th class="px-6 py-3.5">Tytuł rozmowy</th>
                            <th class="px-6 py-3.5">Liczba wiadomości</th>
                            <th class="px-6 py-3.5">Data rozpoczęcia</th>
                            <th class="px-6 py-3.5 text-right">Akcje</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($conversations as $c)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                    {{ $c->agent->name }}
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('chat.index', ['conversation_id' => $c->id]) }}" class="hover:underline text-indigo-600 dark:text-indigo-400 font-medium">
                                        {{ $c->title }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-xs font-mono">
                                    {{ $c->messages->count() }}
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-400 font-mono">
                                    {{ $c->created_at->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-3">
                                    <a href="{{ route('chat.index', ['conversation_id' => $c->id]) }}" class="text-xs text-indigo-600 hover:underline">
                                        Otwórz
                                    </a>
                                    <form method="POST" action="{{ route('chat.destroy', $c) }}" class="inline" onsubmit="return confirm('Usunąć tę rozmowę?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-600 hover:underline">
                                            Usuń
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 text-sm">
                                    {{ __('chat.no_conversations') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($conversations->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $conversations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
