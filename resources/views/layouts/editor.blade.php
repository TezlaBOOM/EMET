<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Edytor Scenariuszy' }} – {{ config('app.name', 'AgentHub') }}</title>

    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Drawflow CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.css">

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/gh/jerosoler/Drawflow/dist/drawflow.min.js"></script>

    <style>
        #drawflow {
            width: 100%;
            height: calc(100vh - 64px);
            background: #090d16;
            background-size: 25px 25px;
            background-image: radial-gradient(circle, #1e293b 1px, transparent 1px);
        }
        .drawflow .drawflow-node {
            background: #0f172a;
            border: 1px solid #334155;
            color: #f8fafc;
            border-radius: 12px;
            min-width: 180px;
            padding: 12px 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .drawflow .drawflow-node.selected {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.4);
        }
        .drawflow .drawflow-node .input, .drawflow .drawflow-node .output {
            background: #6366f1;
            border: 2px solid #0f172a;
            width: 14px;
            height: 14px;
        }
    </style>
</head>
<body class="h-full flex flex-col overflow-hidden font-sans antialiased">
    <!-- Górny pasek narzędzi edytora -->
    <header class="h-16 bg-slate-900 border-b border-slate-800 px-6 flex items-center justify-between shrink-0 z-20">
        <div class="flex items-center gap-4">
            <a href="{{ route('scenarios.index') }}" class="text-slate-400 hover:text-white text-sm font-medium flex items-center gap-1.5 transition-colors">
                &larr; Wróć
            </a>
            <div class="h-5 w-px bg-slate-800"></div>
            <div>
                <h1 class="text-base font-bold text-white tracking-tight flex items-center gap-2">
                    {{ $scenario->name ?? 'Nowy Scenariusz' }}
                    <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-950 text-indigo-400 border border-indigo-800 font-mono">
                        v{{ $scenario->currentVersion?->version ?? '1.0' }}
                    </span>
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" id="btn-save-draft"
                    class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition-all">
                Zapisz szkic
            </button>
            <button type="button" id="btn-publish"
                    class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl shadow-xs shadow-indigo-600/30 transition-all">
                Opublikuj wersję
            </button>
            <button type="button" id="btn-run-test"
                    class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl shadow-xs shadow-emerald-600/30 transition-all flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                </svg>
                Uruchom testowo
            </button>
        </div>
    </header>

    <!-- Główna zawartość kanwy -->
    <main class="flex-1 relative flex overflow-hidden">
        {{ $slot }}
    </main>
</body>
</html>
