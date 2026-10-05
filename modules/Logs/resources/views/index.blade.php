<x-layouts.app active-module="logs" active-subcategory="audit" :title="__('logs.title')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    {{ __('logs.sub_audit') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Rejestr aktywności użytkowników, operacji administracyjnych i zdarzeń systemowych.
                </p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('common.created_at') }}</th>
                            <th class="px-6 py-3.5">{{ __('users.email') }}</th>
                            <th class="px-6 py-3.5">Akcja</th>
                            <th class="px-6 py-3.5">Obiekt</th>
                            <th class="px-6 py-3.5">IP</th>
                            <th class="px-6 py-3.5">Szczegóły</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-3.5 text-xs text-slate-400 whitespace-nowrap">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="px-6 py-3.5 font-medium text-slate-900 dark:text-white">
                                    {{ $log->user?->name ?? 'System' }}
                                </td>
                                <td class="px-6 py-3.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="px-6 py-3.5 text-xs">
                                    {{ $log->entity_type ? class_basename($log->entity_type) . ' #' . $log->entity_id : '—' }}
                                </td>
                                <td class="px-6 py-3.5 text-xs font-mono text-slate-400">
                                    {{ $log->ip_address ?? '127.0.0.1' }}
                                </td>
                                <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-slate-400 truncate max-w-xs font-mono">
                                    {{ $log->details ? json_encode($log->details) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-400">
                                    Brak zarejestrowanych zdarzeń w audycie.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
