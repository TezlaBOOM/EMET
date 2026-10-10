<?php

declare(strict_types=1);

namespace Modules\System\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\SetupProgress;

class SystemConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'system';
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
        $steps = SetupProgress::all()->map(function ($s) {
            return [
                'step_key' => $s->step_key,
                'title' => $s->title,
                'module' => $s->module,
                'is_completed' => $s->is_completed,
            ];
        })->toArray();

        return [
            'version' => config('app.version', '1.5.0'),
            'setup_steps' => $steps,
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
        foreach ($data['setup_steps'] ?? [] as $step) {
            $create[] = $step;
        }

        return [
            'create' => $create,
            'update' => [],
            'skip' => [],
            'conflicts' => [],
        ];
    }

    public function import(array $plan): array
    {
        $count = 0;
        foreach ($plan['create'] ?? [] as $step) {
            SetupProgress::updateOrCreate(
                ['step_key' => $step['step_key']],
                [
                    'title' => $step['title'] ?? $step['step_key'],
                    'module' => $step['module'] ?? 'system',
                    'is_completed' => (bool) ($step['is_completed'] ?? false),
                ]
            );
            $count++;
        }

        return [
            'success' => true,
            'imported_count' => $count,
            'updated_count' => 0,
            'errors' => [],
        ];
    }
}
