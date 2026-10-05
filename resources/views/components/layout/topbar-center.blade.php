@props(['title' => null])

<div class="h-16 flex items-center px-6 flex-1 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-colors">
    @if(isset($slot) && !empty(trim((string)$slot)))
        {{ $slot }}
    @else
        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span class="font-medium text-slate-900 dark:text-white">{{ $title ?? __('dashboard.title') }}</span>
        </div>
    @endif
</div>
