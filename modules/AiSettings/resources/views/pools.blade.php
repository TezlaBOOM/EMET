<x-layouts.app active-module="ai-settings" active-subcategory="pools" :title="__('ai.title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('ai.sub_pools') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Strategie równoważenia obciążenia i automatycznego przełączania awaryjnego (Failover & Cooldown).
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Smart Pool Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                            Smart Reasoning
                        </span>
                        <span class="text-xs text-slate-400 font-mono">pool:smart</span>
                    </div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">
                        Pula Inteligentna (Smart)
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Dedykowana dla zadań logicznych, planowania agentów, analizy kodu oraz syntezy wiedzy.
                    </p>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Modele priorytetowe:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">Claude 3.5 Sonnet, GPT-4o</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Routing:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">Weighted Round-Robin</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Obsługa błędu 429:</span>
                            <span class="text-emerald-600 font-medium">Auto-cooldown 60s & failover</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fast Pool Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            Low Latency
                        </span>
                        <span class="text-xs text-slate-400 font-mono">pool:fast</span>
                    </div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">
                        Pula Szybka (Fast)
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Dedykowana dla streamingu odpowiedzi na żywo, klasyfikacji intencji i prostych podsumowań.
                    </p>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Modele priorytetowe:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">Gemini 2.5 Flash, GPT-4o-mini</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Routing:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">Najniższa latencja</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Obsługa błędu 429:</span>
                            <span class="text-emerald-600 font-medium">Auto-cooldown 60s & failover</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Embeddings Pool Card -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            Vector Search
                        </span>
                        <span class="text-xs text-slate-400 font-mono">pool:embedding</span>
                    </div>
                    <h3 class="mt-3 text-lg font-bold text-slate-900 dark:text-white">
                        Pula Wektorowa (Embedding)
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Dedykowana dla generowania embeddingów wiedzy i pamięci semantycznej w Qdrant/pgvector.
                    </p>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Modele priorytetowe:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">text-embedding-3-small, Ollama</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Wymiarowość:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">1536 / 768 dim</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Obsługa błędu 429:</span>
                            <span class="text-emerald-600 font-medium">Kolejkowanie asynchroniczne</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <h3 class="font-bold text-slate-900 dark:text-white text-base">
                Zasada działania algorytmu routingu
            </h3>
            <div class="mt-3 text-sm text-slate-600 dark:text-slate-300 space-y-2">
                <p>
                    1. <strong>Wybór konta:</strong> Brama LLM pobiera aktywne konta przypisane do danego dostawcy lub puli, filtrując konta w stanie <span class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 px-1 py-0.5 rounded">cooldown</span>.
                </p>
                <p>
                    2. <strong>Ważony algorytm:</strong> Konta o wyższej wadze (np. waga 20 vs waga 10) otrzymują proporcjonalnie więcej zapytań (Weighted Round-Robin).
                </p>
                <p>
                    3. <strong>Detekcja błędu 429 / HTTP Error:</strong> Jeśli dostawca zwróci kod HTTP 429 (Rate Limit Exceeded), konto jest natychmiast oznaczane statusem <span class="font-mono text-xs bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200 px-1 py-0.5 rounded">cooldown</span> na czas 60 sekund, a zapytanie jest automatycznie powtarzane (failover) na kolejnym dostępnym koncie w puli bez przerywania sesji użytkownika.
                </p>
            </div>
        </div>
    </div>
</x-layouts.app>
