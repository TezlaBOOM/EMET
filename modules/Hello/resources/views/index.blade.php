<x-layouts.app active-module="hello" active-subcategory="index" title="Hello Module">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                    Referencyjny Moduł Rozszerzenia (Hello Module)
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Demonstracja modułowej rozszerzalności platformy Projekt-Emet bez konieczności modyfikowania rdzenia.
                </p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 ring-1 ring-indigo-500/20">
                v1.0.0
            </span>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Jak działa rozszerzanie Projekt-Emet:</h2>
            <ol class="list-decimal list-inside space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <li>Utwórz folder w katalogu <code>modules/&lt;Nazwa&gt;/</code>.</li>
                <li>Zdefiniuj manifest <code>module.json</code> z deklaracją nawigacji w Menu 1 i Menu 2 oraz uprawnień RBAC.</li>
                <li>Dodaj pliki tras w <code>routes/web.php</code> lub <code>routes/api.php</code>.</li>
                <li>Umieść szablony Blade w <code>resources/views/</code> – są one automatycznie dostępne pod aliasem <code>module-&lt;slug&gt;::&lt;nazwa&gt;</code>.</li>
            </ol>
        </div>
    </div>
</x-layouts.app>
