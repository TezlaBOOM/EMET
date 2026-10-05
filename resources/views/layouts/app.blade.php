@props(['title' => null, 'activeModule' => 'dashboard', 'activeSubcategory' => 'overview'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' – ' . config('agenthub.name', 'Projekt-Emet') : config('agenthub.name', 'Projekt-Emet') }}</title>

    <!-- Skrypt motywu (zapobieganie migotaniu) -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Tailwind CSS / Vite -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <!-- Fallback CDN dla środowiska developerskiego przed buildem -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            brand: {
                                50: '#eef2ff',
                                500: '#6366f1',
                                600: '#4f46e5',
                                700: '#4338ca',
                            }
                        }
                    }
                }
            }
        </script>
    @endif

    <style>
        /* CSS Grid dla 7 bloków AgentHub */
        .agenthub-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            grid-template-rows: 64px 48px 1fr;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .agenthub-layout {
                grid-template-columns: 1fr;
                grid-template-rows: 64px 48px 1fr;
            }
            .agenthub-sidebar {
                display: none;
            }
        }
    </style>
</head>
<body class="h-full bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 font-sans antialiased overflow-hidden">
    <div class="agenthub-layout">
        <!-- Lewa kolumna: BLOKI 1, 2, 3 -->
        <aside class="agenthub-sidebar flex flex-col border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 z-20"
               style="grid-column: 1; grid-row: 1 / 4;">
            <!-- BLOK 1: Logo -->
            <x-layout.logo />

            <!-- BLOK 2: Menu 1 -->
            <x-layout.menu-primary :active-module="$activeModule" />

            <!-- BLOK 3: Wyloguj -->
            <x-layout.logout />
        </aside>

        <!-- Górny pasek: BLOKI 4 i 5 -->
        <header class="flex border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 z-10"
                style="grid-column: 2; grid-row: 1;">
            <!-- BLOK 4: Górny slot centralny -->
            <x-layout.topbar-center :title="$title">
                {{ $topbarCenter ?? '' }}
            </x-layout.topbar-center>

            <!-- BLOK 5: Informacje o użytkowniku i profil -->
            <x-layout.user-nav />
        </header>

        <!-- Pasek podkategorii: BLOK 6 -->
        <nav class="z-10" style="grid-column: 2; grid-row: 2;">
            <x-layout.menu-secondary :active-module="$activeModule" :active-subcategory="$activeSubcategory" />
        </nav>

        <!-- Główny obszar roboczy: BLOK 7 -->
        <main class="overflow-y-auto p-6 bg-slate-50 dark:bg-slate-950/70"
              style="grid-column: 2; grid-row: 3;">
            {{ $slot }}
        </main>
    </div>

    <!-- Pływający widget czatu (szybki dostęp) -->
    <div class="fixed bottom-6 right-6 z-50">
        <a href="{{ url('/chat') }}"
           class="flex items-center gap-2 px-4 py-3 rounded-full bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-medium shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-105 active:scale-95 transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span class="text-sm font-semibold tracking-wide">{{ __('chat.floating_button') }}</span>
        </a>
    </div>
</body>
</html>
