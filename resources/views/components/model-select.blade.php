@props([
    'pools' => collect(),
    'selectedPoolId' => null,
    'selectedModel' => '',
    'name' => 'primary_model',
    'required' => true,
])

@php
    $allModels = \App\Models\LlmModel::with('provider')->orderBy('sort')->orderBy('model_key')->get();
    $modelsByProvider = $allModels->groupBy(fn($m) => $m->provider->name ?? 'Inne');
    $currentModelObj = $allModels->firstWhere('model_key', $selectedModel);
    $isCurrentModelDisabled = $currentModelObj && ! $currentModelObj->enabled;
@endphp

<div x-data="{
    poolId: '{{ $selectedPoolId }}',
    modelKey: '{{ $selectedModel }}',
    models: {{ json_encode($allModels) }},
    pools: {{ json_encode($pools) }},
    get activePool() {
        return this.pools.find(p => p.id == this.poolId);
    },
    isAllowedByPool(mKey) {
        if (!this.activePool || !this.activePool.allowed_models || this.activePool.allowed_models.length === 0) {
            return true;
        }
        return this.activePool.allowed_models.includes(mKey);
    }
}" class="space-y-2">
    <div>
        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
            {{ __('agents.primary_model') }}
        </label>

        @if($allModels->isNotEmpty())
            <div class="relative">
                <select name="{{ $name }}" x-model="modelKey" @if($required) required @endif
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono text-slate-900 dark:text-white">
                    <option value="">-- Wybierz model --</option>
                    @foreach($modelsByProvider as $providerName => $models)
                        <optgroup label="{{ $providerName }}">
                            @foreach($models as $m)
                                <option value="{{ $m->model_key }}"
                                        @if(!$m->enabled) disabled @endif
                                        @selected($selectedModel === $m->model_key)>
                                    {{ $m->display_name }} ({{ $m->model_key }})
                                    @if(!$m->enabled) [Wyłączony] @endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @else
            <!-- Fallback text input gdy baza nie zawiera jeszcze modeli w rejestrze -->
            <input type="text" name="{{ $name }}" value="{{ $selectedModel }}" @if($required) required @endif
                   placeholder="np. gpt-4o, claude-3-5-sonnet"
                   class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono">
        @endif

        @if($isCurrentModelDisabled)
            <div class="mt-1 flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <span>Uwaga: Ten model jest obecnie wyłączony w rejestrze modeli AI.</span>
            </div>
        @endif
    </div>

    <!-- Podgląd właściwości wybranego modelu -->
    <template x-if="modelKey">
        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
            <template x-for="m in models.filter(item => item.model_key === modelKey)" :key="m.id">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="font-medium text-slate-800 dark:text-slate-200" x-text="m.display_name"></span>
                        <span class="text-slate-400 font-mono text-[10px]" x-text="'(' + (m.context_window ? m.context_window.toLocaleString() + ' tok.' : '') + ')'"></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <template x-for="cap in (m.capabilities || [])" :key="cap">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300" x-text="cap"></span>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
