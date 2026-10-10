<x-layouts.app active-module="scenarios" active-subcategory="create" title="Nowy Scenariusz">
    <div class="max-w-2xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Nowy Scenariusz
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zdefiniuj nazwę scenariusza, po czym przejdziesz bezpośrednio do edytora wizualnego Drawflow.
            </p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
            <form method="POST" action="{{ route('scenarios.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nazwa scenariusza</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Opis</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-2">
                    <a href="{{ route('scenarios.index') }}" class="px-4 py-2 border rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400">
                        Anuluj
                    </a>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-md">
                        Utwórz i przejdź do edytora &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
