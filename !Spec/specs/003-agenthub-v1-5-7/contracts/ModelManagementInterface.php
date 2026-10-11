<?php

declare(strict_types=1);

namespace App\Contracts\Llm;

use App\Models\LlmModel;
use App\Models\LlmProvider;

/**
 * Kontrakt zarządzania centralnym rejestrem modeli LLM oraz nadpisaniami (v1.5.7).
 */
interface ModelManagementInterface
{
    /**
     * Synchronizuje modele od dostawcy z bazą danych, zachowując chronione nadpisania (overrides).
     *
     * @param  array<int, array<string, mixed>>  $remoteModels
     * @return array{created: int, updated: int, preserved_overrides: int}
     */
    public function syncProviderModels(LlmProvider $provider, array $remoteModels): array;

    /**
     * Zapisuje ręczne modyfikacje modelu i oznacza zmienione pola w tablicy overrides.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateModelWithOverrides(LlmModel $model, array $attributes): LlmModel;

    /**
     * Resetuje ręczne nadpisania modelu do wartości domyślnych dostawcy.
     */
    public function resetOverridesToProviderDefaults(LlmModel $model): LlmModel;

    /**
     * Zwraca listę podmiotów (agenci, scenariusze) zależnych od danego modelu.
     *
     * @return array{agents: array<int, string>, scenarios: array<int, string>}
     */
    public function getModelDependencies(LlmModel $model): array;
}
