@props(['activeModule' => 'dashboard'])

@php
    $moduleManager = app(\App\Services\ModuleManager::class);
    $menuItems = $moduleManager->getMenu1(auth()->user());
@endphp

<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1.5 focus:outline-none" aria-label="{{ __('common.primary_navigation') }}">
    @forelse($menuItems as $item)
        @php
            $isActive = ($activeModule === $item['slug']);
        @endphp
        <a href="{{ Route::has($item['route']) ? route($item['route']) : url('/' . $item['slug']) }}"
           @if($isActive) aria-current="page" @endif
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group {{ $isActive ? 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-semibold shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200' }}">
            <span class="w-5 h-5 flex items-center justify-center transition-colors {{ $isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400 group-hover:text-slate-600 dark:group-hover:text-slate-300' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            </span>
            <span class="truncate">{{ $item['name'] }}</span>
        </a>
    @empty
        <div class="px-3 py-2 text-xs text-slate-400 dark:text-slate-500">
            {{ __('common.no_modules_registered') }}
        </div>
    @endforelse
</nav>
