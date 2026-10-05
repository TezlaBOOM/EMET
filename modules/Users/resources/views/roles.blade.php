<x-layouts.app active-module="users" active-subcategory="roles" :title="__('users.sub_roles')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                {{ __('users.sub_roles') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Zdefiniowane w systemie role dostępowe oraz przypisane do nich uprawnienia.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($roles as $r)
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white capitalize">
                            {{ $r->name }}
                        </h2>
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-medium">
                            {{ $r->permissions->count() }} uprawnień
                        </span>
                    </div>

                    <div class="space-y-1.5 pt-2 border-t border-slate-100 dark:border-slate-800">
                        @forelse($r->permissions as $p)
                            <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{{ $p->name }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 italic">Wszystkie uprawnienia administratora</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
