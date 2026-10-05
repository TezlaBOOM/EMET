<x-layouts.app active-module="ai-settings" active-subcategory="limits" :title="__('ai.title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('ai.sub_limits') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zarządzanie progami RPM/TPM, cenniki tokenów oraz szacowanie kosztów operacyjnych.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Domyślny cooldown</span>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">60s</div>
                <span class="text-xs text-slate-400">Po otrzymaniu HTTP 429</span>
            </div>
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Maks. próby failover</span>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">3 konta</div>
                <span class="text-xs text-slate-400">Przed zgłoszeniem błędu</span>
            </div>
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Globalny limit RPM</span>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">300 RPM</div>
                <span class="text-xs text-slate-400">Suma na wszystkie pule</span>
            </div>
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Globalny limit TPM</span>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">2,000,000</div>
                <span class="text-xs text-slate-400">Tokenów na minutę</span>
            </div>
        </div>

        <!-- Tabela szacunkowych kosztów per 1M tokenów -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                    Cennik referencyjny modeli (USD / 1M tokenów)
                </h3>
            </div>
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3.5">Model</th>
                        <th class="px-6 py-3.5">Dostawca</th>
                        <th class="px-6 py-3.5">Input (prompt) / 1M</th>
                        <th class="px-6 py-3.5">Output (completion) / 1M</th>
                        <th class="px-6 py-3.5">Zastosowanie</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                    <tr>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">gemini-2.5-flash</td>
                        <td class="px-6 py-3.5">Google Gemini</td>
                        <td class="px-6 py-3.5 font-mono">$0.075</td>
                        <td class="px-6 py-3.5 font-mono">$0.30</td>
                        <td class="px-6 py-3.5 text-slate-500">Fast Pool, Chat na żywo</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">claude-3-5-sonnet</td>
                        <td class="px-6 py-3.5">Anthropic</td>
                        <td class="px-6 py-3.5 font-mono">$3.00</td>
                        <td class="px-6 py-3.5 font-mono">$15.00</td>
                        <td class="px-6 py-3.5 text-slate-500">Smart Pool, Zadania agentowe</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">gpt-4o</td>
                        <td class="px-6 py-3.5">OpenAI</td>
                        <td class="px-6 py-3.5 font-mono">$2.50</td>
                        <td class="px-6 py-3.5 font-mono">$10.00</td>
                        <td class="px-6 py-3.5 text-slate-500">Smart Pool, Narzędzia i JSON</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3.5 font-bold text-slate-900 dark:text-white">ollama/*</td>
                        <td class="px-6 py-3.5">Lokalny Ollama</td>
                        <td class="px-6 py-3.5 font-mono text-emerald-600 font-semibold">$0.00 (Self-hosted)</td>
                        <td class="px-6 py-3.5 font-mono text-emerald-600 font-semibold">$0.00 (Self-hosted)</td>
                        <td class="px-6 py-3.5 text-slate-500">Prywatne zadania offline</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
