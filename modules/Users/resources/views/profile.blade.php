<x-layouts.app active-module="users" active-subcategory="profile" :title="__('users.sub_profile')">
    <div class="max-w-2xl space-y-6">
        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('users.sub_profile') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zarządzaj swoimi danymi konta, motywem interfejsu i hasłem.
            </p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <form method="POST" action="{{ route('users.profile.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('users.name') }}
                    </label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('users.email') }}
                    </label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ __('users.theme') }}
                    </label>
                    <select name="theme" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                        <option value="system" {{ $user->theme === 'system' ? 'selected' : '' }}>Systemowy (automatyczny)</option>
                        <option value="light" {{ $user->theme === 'light' ? 'selected' : '' }}>Jasny</option>
                        <option value="dark" {{ $user->theme === 'dark' ? 'selected' : '' }}>Ciemny</option>
                    </select>
                </div>

                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ __('users.new_password') }}
                        </label>
                        <input type="password" name="password" minlength="6" placeholder="••••••••"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                            {{ __('users.password_confirmation') }}
                        </label>
                        <input type="password" name="password_confirmation" minlength="6" placeholder="••••••••"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm">
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-md shadow-indigo-600/20 transition-all">
                        {{ __('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
