<?php

declare(strict_types=1);

namespace Modules\Agents\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;

class AgentsConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'agents';
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
        $agents = Agent::all();

        return [
            'version' => $this->schemaVersion(),
            'records' => $agents->map(function (Agent $agent) {
                return [
                    'name' => $agent->name,
                    'slug' => $agent->slug,
                    'system_prompt' => $agent->system_prompt,
                    'model' => $agent->model,
                    'status' => $agent->status,
                    'internet_mode' => $agent->internet_mode,
                    'context_mode' => $agent->context_mode,
                    'context_window_messages' => $agent->context_window_messages,
                ];
            })->all(),
        ];
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (! isset($data['records']) || ! is_array($data['records'])) {
            $errors[] = 'Brak wymaganej tablicy records w sekcji agents.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function plan(array $data, array $options): array
    {
        $create = [];
        $update = [];
        $skip = [];
        $conflicts = [];

        $mode = $options['mode'] ?? 'merge';
        $records = $data['records'] ?? [];

        foreach ($records as $item) {
            $existing = Agent::where('slug', $item['slug'])->first();
            if (! $existing) {
                $create[] = $item;
            } elseif ($mode === 'overwrite') {
                $update[] = $item;
            } elseif ($mode === 'new_only') {
                $skip[] = $item;
            } else {
                // merge
                $update[] = $item;
            }
        }

        return [
            'create' => $create,
            'update' => $update,
            'skip' => $skip,
            'conflicts' => $conflicts,
        ];
    }

    public function import(array $plan): array
    {
        $importedCount = 0;
        $updatedCount = 0;
        $errors = [];

        DB::transaction(function () use ($plan, &$importedCount, &$updatedCount) {
            foreach ($plan['create'] ?? [] as $item) {
                Agent::create([
                    'name' => $item['name'],
                    'slug' => $item['slug'],
                    'system_prompt' => $item['system_prompt'],
                    'model' => $item['model'] ?? 'gpt-4o',
                    'status' => $item['status'] ?? 'active',
                    'internet_mode' => $item['internet_mode'] ?? 'off',
                    'context_mode' => $item['context_mode'] ?? 'stateful',
                    'context_window_messages' => $item['context_window_messages'] ?? null,
                ]);
                $importedCount++;
            }

            foreach ($plan['update'] ?? [] as $item) {
                $agent = Agent::where('slug', $item['slug'])->first();
                if ($agent) {
                    $agent->update([
                        'name' => $item['name'],
                        'system_prompt' => $item['system_prompt'],
                        'model' => $item['model'] ?? 'gpt-4o',
                        'status' => $item['status'] ?? 'active',
                        'internet_mode' => $item['internet_mode'] ?? 'off',
                        'context_mode' => $item['context_mode'] ?? 'stateful',
                        'context_window_messages' => $item['context_window_messages'] ?? null,
                    ]);
                    $updatedCount++;
                }
            }
        });

        return [
            'success' => true,
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ];
    }
}
