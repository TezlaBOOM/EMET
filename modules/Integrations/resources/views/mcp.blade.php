<x-layouts.app active-module="integrations" active-subcategory="mcp" :title="__('integrations.mcp_title')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('integrations.mcp_title') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('integrations.mcp_subtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formularz dodawania zewnętrznego serwera MCP (MCP Client) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <h3 class="font-bold text-slate-900 dark:text-white text-base">
                    {{ __('integrations.mcp_add_server') }}
                </h3>
                <form method="POST" action="{{ route('integrations.mcp.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.mcp_server_name') }}
                        </label>
                        <input type="text" name="name" required placeholder="np. GitHub MCP / PostgreSQL MCP"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.mcp_transport') }}
                        </label>
                        <select name="transport" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs">
                            <option value="streamable_http">Streamable HTTP (JSON-RPC 2.0)</option>
                            <option value="http_sse">HTTP + Server-Sent Events (SSE)</option>
                            <option value="stdio">Local Process (stdio)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.mcp_endpoint_url') }}
                        </label>
                        <input type="text" name="endpoint_url" placeholder="https://mcp.example.com/rpc"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ __('integrations.mcp_auth_token') }}
                        </label>
                        <input type="password" name="auth_token" placeholder="Bearer Token (opcjonalnie, szyfrowany)"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs">
                    </div>

                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-xs transition">
                        {{ __('integrations.mcp_connect_and_sync') }}
                    </button>
                </form>
            </div>

            <!-- Lista podłączonych serwerów MCP + Wbudowany Serwer MCP Projekt-Emet -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Wbudowany Serwer MCP Projekt-Emet -->
                <div class="bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-800 rounded-2xl p-6 shadow-xs">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                                MCP Server Endpoint (JSON-RPC 2.0)
                            </span>
                            <h3 class="mt-2 font-bold text-slate-900 dark:text-white text-base">
                                {{ __('integrations.mcp_builtin_server') }}
                            </h3>
                        </div>
                        <span class="font-mono text-xs text-slate-500">POST /api/v1/mcp/rpc</span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('integrations.mcp_builtin_desc') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($serverManifest['tools'] as $tool)
                            <span class="px-2.5 py-1 rounded-lg text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                {{ $tool['name'] }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Podłączone zewnętrzne serwery MCP -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
                    <div class="p-5 border-b border-slate-200 dark:border-slate-800">
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">
                            {{ __('integrations.mcp_connected_servers') }}
                        </h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($servers as $srv)
                            <div class="p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-900 dark:text-white">{{ $srv->name }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                            {{ $srv->transport }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                            Skille: {{ $srv->synced_skills_count }}
                                        </span>
                                    </div>
                                    <p class="text-xs font-mono text-slate-500">{{ $srv->endpoint_url ?: ($srv->command ?: 'local-mcp') }}</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('integrations.mcp.sync', $srv) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300 transition">
                                            {{ __('integrations.mcp_sync_skills') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('integrations.mcp.destroy', $srv) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 transition">
                                            {{ __('integrations.mcp_disconnect') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-xs text-slate-400">
                                {{ __('integrations.mcp_no_servers') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>

