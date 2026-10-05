@props(['collapsed' => false])

<div class="h-16 flex items-center px-4 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-colors">
    <a href="{{ url('/') }}" class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-lg p-1">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 flex items-center justify-center text-white shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        <div class="flex flex-col">
            <span class="font-bold text-lg leading-tight tracking-tight text-slate-900 dark:text-white font-sans">
                {{ config('agenthub.name', 'Projekt-Emet') }}
            </span>
            <span class="text-[10px] uppercase font-semibold tracking-wider text-indigo-600 dark:text-indigo-400">
                v{{ config('agenthub.version', '1.0.0') }}
            </span>
        </div>
    </a>
</div>
