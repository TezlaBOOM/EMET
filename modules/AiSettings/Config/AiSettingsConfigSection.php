<?php

declare(strict_types=1);

namespace Modules\AiSettings\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\LlmAccountPool;
use App\Models\LlmProvider;

class AiSettingsConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'ai_settings';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dependsOn(): array
    {
        return [];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function export(array $options): iterable
    {
        $providers = LlmProvider::all()->map(function (LlmProvider $p): array {
            return [
                'name' => $p->name,
                'slug' => $p->slug,
                'driver' => $p->driver,
                'base_url' => $p->base_url,
                'default_model' => $p->default_model,
                'is_active' => (bool) $p->is_active,
            ];
        })->all();

        $pools = LlmAccountPool::all()->map(function (LlmAccountPool $pool): array {
            return [
                'name' => $pool->name,
                'slug' => $pool->slug,
                'strategy' => $pool->strategy,
                'allowed_models' => $pool->allowed_models,
                'is_active' => (bool) $pool->is_active,
            ];
        })->all();

        return [
            'version' => $this->schemaVersion(),
            'providers' => $providers,
            'pools' => $pools,
            'gateway_policies' => [
                'semantic_cache_enabled' => (bool) config('agenthub.llm.semantic_cache.enabled', false),
                'semantic_cache_threshold' => (float) config('agenthub.llm.semantic_cache.similarity_threshold', 0.95),
                'guardrails_enabled' => (bool) config('agenthub.llm.guardrails.enabled', true),
                'jailbreak_action' => (string) config('agenthub.llm.guardrails.jailbreak_action', 'block'),
                'dlp_enabled' => (bool) config('agenthub.llm.guardrails.dlp_enabled', true),
            ],
        ];
    }

    public function validate(array $data): array
    {
        return [
            'is_valid' => true,
            'errors' => [],
        ];
    }

    public function plan(array $data, array $options): array
    {
        $create = [];
        $update = [];

        foreach ($data['pools'] ?? [] as $poolItem) {
            $exists = LlmAccountPool::where('slug', $poolItem['slug'])->exists();
            if ($exists) {
                $update[] = $poolItem;
            } else {
                $create[] = $poolItem;
            }
        }

        return [
            'create' => $create,
            'update' => $update,
            'skip' => [],
            'conflicts' => [],
        ];
    }

    public function import(array $plan): array
    {
        $imported = 0;
        $updated = 0;

        foreach ($plan['create'] ?? [] as $poolItem) {
            LlmAccountPool::updateOrCreate(
                ['slug' => $poolItem['slug']],
                [
                    'name' => $poolItem['name'] ?? $poolItem['slug'],
                    'strategy' => $poolItem['strategy'] ?? 'weighted',
                    'allowed_models' => $poolItem['allowed_models'] ?? null,
                    'is_active' => (bool) ($poolItem['is_active'] ?? true),
                ]
            );
            $imported++;
        }

        foreach ($plan['update'] ?? [] as $poolItem) {
            LlmAccountPool::updateOrCreate(
                ['slug' => $poolItem['slug']],
                [
                    'name' => $poolItem['name'] ?? $poolItem['slug'],
                    'strategy' => $poolItem['strategy'] ?? 'weighted',
                    'allowed_models' => $poolItem['allowed_models'] ?? null,
                    'is_active' => (bool) ($poolItem['is_active'] ?? true),
                ]
            );
            $updated++;
        }

        return [
            'success' => true,
            'imported_count' => $imported,
            'updated_count' => $updated,
            'errors' => [],
        ];
    }
}

