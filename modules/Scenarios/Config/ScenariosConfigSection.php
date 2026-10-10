<?php

declare(strict_types=1);

namespace Modules\Scenarios\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\Scenario;
use App\Models\ScenarioVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScenariosConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'scenarios';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dependsOn(): array
    {
        return ['agents', 'skills'];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function export(array $options): iterable
    {
        $scenarios = Scenario::with('currentVersion')->get();

        return [
            'version' => $this->schemaVersion(),
            'records' => $scenarios->map(function (Scenario $s) {
                return [
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'description' => $s->description,
                    'status' => $s->status,
                    'graph' => $s->currentVersion?->graph ?? [],
                ];
            })->all(),
        ];
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (! isset($data['records']) || ! is_array($data['records'])) {
            $errors[] = 'Brak wymaganej tablicy records w sekcji scenarios.';
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

        $records = $data['records'] ?? [];
        foreach ($records as $item) {
            $existing = Scenario::where('slug', $item['slug'])->first();
            if (! $existing) {
                $create[] = $item;
            } else {
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

        DB::transaction(function () use ($plan, &$importedCount) {
            foreach ($plan['create'] ?? [] as $item) {
                $scenario = Scenario::create([
                    'slug' => $item['slug'],
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'status' => $item['status'] ?? 'draft',
                    'created_by' => User::first()?->id ?? 1,
                ]);

                $version = ScenarioVersion::create([
                    'scenario_id' => $scenario->id,
                    'version' => 1,
                    'graph' => $item['graph'] ?? [],
                    'draft' => false,
                ]);

                $scenario->update(['current_version_id' => $version->id]);
                $importedCount++;
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
