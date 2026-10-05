@php
    $user = auth()->user();
    $roleName = $user ? ($user->roles->first()?->name ?? 'User') : 'Guest';
@endphp

<div class="h-16 flex items-center justify-end px-6 gap-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-colors">
    <!-- Przełącznik motywu Jasny / Ciemny -->
    <button type="button"
            onclick="document.documentElement.classList.toggle('dark'); localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');"
            class="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
            title="{{ __('common.toggle_theme') }}"
            aria-label="{{ __('common.toggle_theme') }}">
        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
    </button>

    @if($user)
        <a href="{{ Route::has('users.profile') ? route('users.profile') : url('/profile') }}" class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg p-1">
            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-semibold flex items-center justify-center text-xs ring-2 ring-indigo-500/20">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div class="hidden sm:flex flex-col text-left">
                <span class="text-xs font-semibold text-slate-800 dark:text-slate-200 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    {{ $user->name }}
                </span>
                <span class="text-[10px] text-slate-400 dark:text-slate-500 uppercase tracking-wider font-medium">
                    {{ $roleName }}
                </span>
            </div>
        </a>
    @endif
</div>
