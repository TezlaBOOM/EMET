@props(['activeModule' => 'dashboard', 'activeSubcategory' => 'overview'])

@php
    $moduleManager = app(\App\Services\ModuleManager::class);
    $subcategories = $moduleManager->getMenu2($activeModule, auth()->user());
@endphp

<div class="h-12 flex items-center px-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/40 backdrop-blur-xs transition-colors overflow-x-auto">
    <div class="flex items-center gap-1 sm:gap-2">
        @forelse($subcategories as $sub)
            @php
                $isActive = ($activeSubcategory === ($sub['id'] ?? ''));
                $targetRoute = $sub['route'] ?? null;
                $url = $targetRoute && Route::has($targetRoute) ? route($targetRoute) : url('/' . $activeModule . '/' . ($sub['id'] ?? ''));
            @endphp
            <a href="{{ $url }}"
               @if($isActive) aria-current="page" @endif
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all whitespace-nowrap {{ $isActive ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-2xs font-semibold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800/40' }}">
                {{ __($sub['title'] ?? $sub['id']) }}
            </a>
        @empty
            <span class="text-xs text-slate-400 dark:text-slate-500 italic">
                {{ __('common.default_view') }}
            </span>
        @endforelse
    </div>
</div>
