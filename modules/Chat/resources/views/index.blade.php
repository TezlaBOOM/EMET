<x-layouts.app active-module="chat" active-subcategory="new" :title="__('chat.title')">
    <div class="h-[calc(100vh-140px)] flex flex-col md:flex-row gap-6">
        <!-- Lewa kolumna: Lista konwersacji i wybór agenta -->
        <div class="w-full md:w-80 flex-shrink-0 flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h2 class="font-bold text-sm text-slate-900 dark:text-white">
                    {{ __('chat.history') }}
                </h2>
                <button type="button"
                        onclick="document.getElementById('modal-new-chat').classList.remove('hidden')"
                        class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold">
                    + {{ __('chat.new_conversation') }}
                </button>
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60 p-2 space-y-1">
                @forelse($conversations as $c)
                    <a href="{{ route('chat.index', ['conversation_id' => $c->id]) }}"
                       class="block p-3 rounded-xl transition-all text-xs
                       {{ ($activeConversation && $activeConversation->id === $c->id) ? 'bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800/60 font-medium' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-semibold text-slate-900 dark:text-white truncate">
                                {{ $c->agent->name }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">
                                {{ $c->created_at->format('H:i') }}
                            </span>
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 truncate text-[11px]">
                            {{ $c->title }}
                        </p>
                    </a>
                @empty
                    <div class="p-6 text-center text-xs text-slate-400">
                        {{ __('chat.no_conversations') }}
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Prawa kolumna: Obszar aktywnego czatu -->
        <div class="flex-1 flex flex-col bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            @if($activeConversation)
                <!-- Nagłówek czatu -->
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-600/10 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center font-bold text-indigo-600 dark:text-indigo-400 text-sm">
                            {{ strtoupper(substr($activeConversation->agent->name, 0, 2)) }}
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm">
                                {{ $activeConversation->agent->name }}
                            </h3>
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <span class="font-mono">{{ $activeConversation->agent->primary_model }}</span>
                                <span>&bull;</span>
                                <span class="uppercase text-[10px] font-semibold text-indigo-500">{{ $activeConversation->agent->runtime_type }}</span>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('chat.destroy', $activeConversation) }}" onsubmit="return confirm('Usunąć tę rozmowę?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-slate-400 hover:text-rose-500 transition-colors">
                            Usuń rozmowę
                        </button>
                    </form>
                </div>

                <!-- Wiadomości -->
                <div id="messages-container" class="flex-1 overflow-y-auto p-6 space-y-4">
                    @forelse($activeConversation->messages as $msg)
                        <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm
                                {{ $msg->role === 'user' ? 'bg-indigo-600 text-white rounded-br-xs shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-bl-xs border border-slate-200/50 dark:border-slate-700/50' }}">
                                <div class="whitespace-pre-wrap leading-relaxed">{{ $msg->content }}</div>
                                @if($msg->role === 'assistant' && isset($msg->metadata['latency_ms']))
                                    <div class="mt-1 pt-1 border-t border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between text-[10px] text-slate-400 font-mono">
                                        <span>{{ $msg->metadata['model'] ?? '' }}</span>
                                        <span>{{ $msg->metadata['latency_ms'] }} ms</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex items-center justify-center text-center p-8 text-slate-400 text-xs">
                            Rozpocznij konwersację z agentem {{ $activeConversation->agent->name }} wpisując wiadomość poniżej.
                        </div>
                    @endforelse
                </div>

                <!-- Formularz wysyłania wiadomości -->
                <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                    <form id="chat-form" onsubmit="handleSendMessage(event)" class="flex gap-2">
                        <textarea id="chat-input"
                                  rows="2"
                                  placeholder="{{ __('chat.placeholder') }}"
                                  required
                                  onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); document.getElementById('chat-form').requestSubmit(); }"
                                  class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm resize-none focus:outline-hidden focus:ring-2 focus:ring-indigo-500"></textarea>
                        <button type="submit"
                                id="btn-send"
                                class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1 self-end">
                            {{ __('chat.send') }}
                        </button>
                    </form>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center text-indigo-600 dark:text-indigo-400 mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-900 dark:text-white text-lg">
                        {{ __('chat.title') }}
                    </h3>
                    <p class="text-xs text-slate-500 max-w-sm mt-1 mb-4">
                        Wybierz istniejącą rozmowę z lewego panelu lub utwórz nową z wybranym agentem.
                    </p>
                    <button type="button"
                            onclick="document.getElementById('modal-new-chat').classList.remove('hidden')"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold">
                        + {{ __('chat.new_conversation') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal nowej rozmowy -->
    <div id="modal-new-chat" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                {{ __('chat.new_conversation') }}
            </h2>
            <form method="POST" action="{{ route('chat.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('chat.select_agent') }}
                    </label>
                    <select name="agent_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        @foreach($agents as $a)
                            <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->primary_model }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        Pierwsza wiadomość (opcjonalnie)
                    </label>
                    <textarea name="initial_message" rows="3" placeholder="Zadaj pytanie agentowi..." class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-new-chat').classList.add('hidden')"
                            class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold">
                        Rozpocznij
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($activeConversation)
        <script>
            function scrollToBottom() {
                const container = document.getElementById('messages-container');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }
            scrollToBottom();

            function handleSendMessage(e) {
                e.preventDefault();
                const input = document.getElementById('chat-input');
                const btn = document.getElementById('btn-send');
                const content = input.value.trim();
                if (!content) return;

                const container = document.getElementById('messages-container');

                // Dodaj dymek użytkownika
                const userBubble = document.createElement('div');
                userBubble.className = 'flex justify-end';
                userBubble.innerHTML = `<div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm bg-indigo-600 text-white rounded-br-xs shadow-xs"><div class="whitespace-pre-wrap leading-relaxed">${escapeHtml(content)}</div></div>`;
                container.appendChild(userBubble);
                scrollToBottom();

                input.value = '';
                btn.disabled = true;

                // Placeholder asystenta
                const botBubble = document.createElement('div');
                botBubble.className = 'flex justify-start';
                const botInner = document.createElement('div');
                botInner.className = 'max-w-[80%] rounded-2xl px-4 py-3 text-sm bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-bl-xs border border-slate-200/50 dark:border-slate-700/50';
                botInner.innerHTML = '<span class="animate-pulse text-xs text-slate-400">Agent generuje odpowiedź...</span>';
                botBubble.appendChild(botInner);
                container.appendChild(botBubble);
                scrollToBottom();

                fetch('{{ route('chat.messages.send', $activeConversation) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ content: content })
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success && data.assistant_message) {
                        botInner.innerHTML = `<div class="whitespace-pre-wrap leading-relaxed">${escapeHtml(data.assistant_message.content)}</div>`;
                        scrollToBottom();
                    } else {
                        botInner.innerHTML = '<span class="text-rose-500 text-xs">Wystąpił błąd podczas generowania odpowiedzi.</span>';
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    botInner.innerHTML = `<span class="text-rose-500 text-xs">Błąd: ${escapeHtml(err.message)}</span>`;
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.innerText = text;
                return div.innerHTML;
            }
        </script>
    @endif
</x-layouts.app>
