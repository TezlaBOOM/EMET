<div class="p-3 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mt-auto transition-colors">
    <form method="POST" action="{{ Route::has('logout') ? route('logout') : url('/logout') }}">
        @csrf
        <button type="submit"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors group focus:outline-none focus:ring-2 focus:ring-rose-500">
            <svg class="w-5 h-5 text-rose-500 group-hover:text-rose-600 dark:group-hover:text-rose-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span class="truncate">{{ __('auth.logout') }}</span>
        </button>
    </form>
</div>
