<x-layouts.app active-module="users" active-subcategory="list" :title="__('users.title')">
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
                    {{ __('users.sub_users') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Zarządzanie kontami użytkowników oraz przypisywanie ról w systemie.
                </p>
            </div>
            <button type="button"
                    onclick="document.getElementById('modal-add-user').classList.remove('hidden')"
                    class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                + {{ __('users.add_user') }}
            </button>
        </div>

        <!-- Tabela użytkowników -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('users.name') }}</th>
                            <th class="px-6 py-3.5">{{ __('users.email') }}</th>
                            <th class="px-6 py-3.5">{{ __('users.role') }}</th>
                            <th class="px-6 py-3.5">{{ __('users.theme') }}</th>
                            <th class="px-6 py-3.5 text-right">{{ __('users.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($users as $u)
                            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                    {{ $u->name }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ $u->email }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">
                                        {{ $u->roles->first()?->name ?? 'brak' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 capitalize text-xs">
                                    {{ $u->theme }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline text-xs font-medium">
                                                {{ __('users.delete') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal: Dodaj użytkownika -->
    <div id="modal-add-user" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">
                {{ __('users.add_user') }}
            </h2>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('users.name') }}
                    </label>
                    <input type="text" name="name" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('users.email') }}
                    </label>
                    <input type="email" name="email" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('users.password') }}
                    </label>
                    <input type="password" name="password" required minlength="6" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                        {{ __('users.role') }}
                    </label>
                    <select name="role" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm">
                        @foreach($roles as $r)
                            <option value="{{ $r->name }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('modal-add-user').classList.add('hidden')"
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
</x-layouts.app>
