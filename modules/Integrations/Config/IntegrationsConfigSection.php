<?php

declare(strict_types=1);

namespace Modules\Integrations\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\AutoConfigProfile;
use App\Models\IntegrationContainer;
use App\Models\IntegrationInstance;
use Illuminate\Support\Facades\DB;

class IntegrationsConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'integrations';
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
        return [
            'instances.*.config.api_key',
            'instances.*.config.auth_token',
        ];
    }

    public function export(array $options): iterable
    {
        $instances = IntegrationInstance::all()->map(function ($instance) use ($options) {
            $config = $instance->config ?? [];
            if (! ($options['include_secrets'] ?? false)) {
                unset($config['api_key'], $config['auth_token']);
            }

            return [
                'name' => $instance->name,
                'slug' => $instance->slug,
                'type' => $instance->type,
                'mode' => $instance->mode,
                'port' => $instance->port,
                'config' => $config,
            ];
        })->toArray();

        $profiles = AutoConfigProfile::all()->map(function ($profile) {
            return [
                'name' => $profile->name,
                'slug' => $profile->slug,
                'adapter_type' => $profile->adapter_type,
                'target_model' => $profile->target_model,
                'steps' => $profile->steps,
                'is_active' => $profile->is_active,
            ];
        })->toArray();

        $containers = IntegrationContainer::where('adoption_mode', '!=', 'none')->get()->map(function ($c) {
            return [
                'container_id' => $c->container_id,
                'name' => $c->name,
                'image' => $c->image,
                'detected_type' => $c->detected_type,
                'adoption_mode' => $c->adoption_mode,
                'adapter_type' => $c->adapter_type,
                'config' => $c->config,
            ];
        })->toArray();

        return [
            'instances' => $instances,
            'profiles' => $profiles,
            'containers' => $containers,
        ];
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (! isset($data['instances']) && ! isset($data['profiles'])) {
            $errors[] = 'Brak wymaganych sekcji (instances lub profiles) w danych integracji.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function plan(array $data, array $options): array
    {
        $mode = $options['mode'] ?? 'merge';
        $create = [];
        $update = [];
        $skip = [];
        $conflicts = [];

        foreach ($data['profiles'] ?? [] as $profileData) {
            $existing = AutoConfigProfile::where('slug', $profileData['slug'])->first();
            if ($existing) {
                if ($mode === 'overwrite') {
                    $update[] = ['type' => 'profile', 'data' => $profileData, 'id' => $existing->id];
                } elseif ($mode === 'new_only') {
                    $skip[] = ['type' => 'profile', 'data' => $profileData];
                } else {
                    $conflicts[] = ['type' => 'profile', 'data' => $profileData, 'conflict' => 'Slug already exists'];
                }
            } else {
                $create[] = ['type' => 'profile', 'data' => $profileData];
            }
        }

        foreach ($data['instances'] ?? [] as $instData) {
            $existing = IntegrationInstance::where('slug', $instData['slug'])->first();
            if ($existing) {
                if ($mode === 'overwrite') {
                    $update[] = ['type' => 'instance', 'data' => $instData, 'id' => $existing->id];
                } elseif ($mode === 'new_only') {
                    $skip[] = ['type' => 'instance', 'data' => $instData];
                } else {
                    $conflicts[] = ['type' => 'instance', 'data' => $instData, 'conflict' => 'Slug exists'];
                }
            } else {
                $create[] = ['type' => 'instance', 'data' => $instData];
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

        DB::beginTransaction();
        try {
            foreach ($plan['create'] ?? [] as $item) {
                if (($item['type'] ?? '') === 'profile') {
                    AutoConfigProfile::create($item['data']);
                    $importedCount++;
                } elseif (($item['type'] ?? '') === 'instance') {
                    IntegrationInstance::create($item['data']);
                    $importedCount++;
                }
            }

            foreach ($plan['update'] ?? [] as $item) {
                if (($item['type'] ?? '') === 'profile') {
                    $profile = AutoConfigProfile::find($item['id']);
                    $profile?->update($item['data']);
                    $updatedCount++;
                } elseif (($item['type'] ?? '') === 'instance') {
                    $instance = IntegrationInstance::find($item['id']);
                    $instance?->update($item['data']);
                    $updatedCount++;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'imported_count' => 0,
                'updated_count' => 0,
                'errors' => [$e->getMessage()],
            ];
        }
    }
}
