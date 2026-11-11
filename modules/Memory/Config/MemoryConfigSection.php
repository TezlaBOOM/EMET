<?php

declare(strict_types=1);

namespace Modules\Memory\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\MemoryCollection;

class MemoryConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'memory';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dependsOn(): array
    {
        return ['ai_settings'];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function export(array $options): iterable
    {
        $collections = MemoryCollection::all()->map(function (MemoryCollection $col): array {
            return [
                'name' => $col->name,
                'slug' => $col->slug,
                'vector_store' => $col->vector_store,
                'embedding_model' => $col->embedding_model,
                'dimensions' => (int) $col->dimensions,
                'distance_metric' => $col->distance_metric,
                'is_default' => (bool) $col->is_default,
            ];
        })->all();

        return [
            'version' => $this->schemaVersion(),
            'collections' => $collections,
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
        return [
            'create' => $data['collections'] ?? [],
            'update' => [],
            'skip' => [],
            'conflicts' => [],
        ];
    }

    public function import(array $plan): array
    {
        return [
            'success' => true,
            'imported_count' => 0,
            'updated_count' => 0,
            'errors' => [],
        ];
    }
}

