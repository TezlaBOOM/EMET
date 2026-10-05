<x-layouts.app active-module="users" active-subcategory="security" :title="__('users.sub_security')">
    <div class="max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('users.sub_security') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Polityka haseł, aktywne sesje i zalecenia bezpieczeństwa platformy.
            </p>
        </div>

        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">
                Status bezpieczeństwa
            </h2>
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200 text-xs space-y-2">
                <p class="font-semibold">Informacja dla instancji wystawionych do sieci publicznej:</p>
                <p>Zaleca się zmianę domyślnego hasła konta administratora (<code>admin@admin.lan</code>) w zakładce „Mój profil”. Platforma nie blokuje pracy przy domyślnym haśle, jednak jest to zalecana praktyka (zgodnie z <code>docs/SECURITY.md</code>).</p>
            </div>
            <div class="pt-2 text-xs text-slate-500 dark:text-slate-400">
                Szyfrowanie bazy danych: <strong>Aktywne</strong> (AES-256-CBC dla poświadczeń AI i tokenów).
            </div>
        </div>
    </div>
</x-layouts.app>
