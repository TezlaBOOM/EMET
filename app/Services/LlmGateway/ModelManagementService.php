<?php

declare(strict_types=1);

namespace App\Services\LlmGateway;

use App\Contracts\Llm\ModelManagementInterface;
use App\Models\Agent;
use App\Models\LlmModel;
use App\Models\LlmProvider;
use App\Models\Scenario;

class ModelManagementService implements ModelManagementInterface
{
    /**
     * Synchronizuje modele od dostawcy z bazą danych, zachowując chronione nadpisania (overrides).
     *
     * @param  array<int, array<string, mixed>>  $remoteModels
     * @return array{created: int, updated: int, preserved_overrides: int}
     */
    public function syncProviderModels(LlmProvider $provider, array $remoteModels): array
    {
        $created = 0;
        $updated = 0;
        $preservedOverrides = 0;

        foreach ($remoteModels as $remote) {
            $modelKey = (string) ($remote['model_key'] ?? $remote['id'] ?? '');
            if ($modelKey === '') {
                continue;
            }

            $existing = LlmModel::where('provider_id', $provider->id)
                ->where('model_key', $modelKey)
                ->first();

            $remotePayload = [
                'display_name' => $remote['display_name'] ?? $remote['name'] ?? $modelKey,
                'description' => $remote['description'] ?? null,
                'capabilities' => $remote['capabilities'] ?? ['chat'],
                'context_window' => (int) ($remote['context_window'] ?? 4096),
                'max_output_tokens' => isset($remote['max_output_tokens']) ? (int) $remote['max_output_tokens'] : null,
                'parameters' => $remote['parameters'] ?? null,
            ];

            if (! $existing) {
                LlmModel::create(array_merge($remotePayload, [
                    'provider_id' => $provider->id,
                    'model_key' => $modelKey,
                    'enabled' => true,
                    'is_manual' => false,
                    'overrides' => [],
                    'tags' => $remote['tags'] ?? [],
                    'sort' => (int) ($remote['sort'] ?? 0),
                ]));
                $created++;
            } else {
                $overrides = $existing->overrides ?? [];
                $hadPreserved = false;
                $updateData = [];

                foreach ($remotePayload as $field => $val) {
                    if (in_array($field, $overrides, true)) {
                        $hadPreserved = true;
                    } else {
                        $updateData[$field] = $val;
                    }
                }

                if ($hadPreserved) {
                    $preservedOverrides++;
                }

                if (! empty($updateData)) {
                    $existing->update($updateData);
                }
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'preserved_overrides' => $preservedOverrides,
        ];
    }

    /**
     * Zapisuje ręczne modyfikacje modelu i oznacza zmienione pola w tablicy overrides.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateModelWithOverrides(LlmModel $model, array $attributes): LlmModel
    {
        $overrides = $model->overrides ?? [];
        $overridableFields = [
            'display_name',
            'description',
            'capabilities',
            'context_window',
            'max_output_tokens',
            'parameters',
            'enabled',
            'tags',
            'sort',
        ];

        foreach ($overridableFields as $field) {
            if (array_key_exists($field, $attributes)) {
                $newVal = $attributes[$field];
                $oldVal = $model->{$field};

                if ($newVal !== $oldVal && ! in_array($field, $overrides, true)) {
                    $overrides[] = $field;
                }
                $model->{$field} = $newVal;
            }
        }

        $model->overrides = array_values(array_unique($overrides));
        $model->save();

        return $model;
    }

    /**
     * Resetuje ręczne nadpisania modelu do wartości domyślnych dostawcy.
     */
    public function resetOverridesToProviderDefaults(LlmModel $model): LlmModel
    {
        $model->overrides = [];
        $model->save();

        return $model;
    }

    /**
     * Zwraca listę podmiotów (agenci, scenariusze) zależnych od danego modelu.
     *
     * @return array{agents: array<int, string>, scenarios: array<int, string>}
     */
    public function getModelDependencies(LlmModel $model): array
    {
        $dependentAgents = Agent::where('primary_model', $model->model_key)
            ->orWhere('fallback_model', $model->model_key)
            ->pluck('name')
            ->all();

        $dependentScenarios = [];
        $scenarios = Scenario::all();
        foreach ($scenarios as $scenario) {
            $nodes = $scenario->graph['nodes'] ?? $scenario->draft_graph['nodes'] ?? [];
            foreach ($nodes as $node) {
                if (isset($node['config']['model']) && $node['config']['model'] === $model->model_key) {
                    $dependentScenarios[] = $scenario->name;
                    break;
                }
            }
        }

        return [
            'agents' => array_values(array_unique($dependentAgents)),
            'scenarios' => array_values(array_unique($dependentScenarios)),
        ];
    }
}
