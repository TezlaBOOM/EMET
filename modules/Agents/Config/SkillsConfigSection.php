<?php

declare(strict_types=1);

namespace Modules\Agents\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\Skill;
use App\Models\SkillVersion;
use Illuminate\Support\Facades\DB;

class SkillsConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'skills';
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
        $skills = Skill::with('versions')->get();

        return [
            'version' => $this->schemaVersion(),
            'records' => $skills->map(function (Skill $skill) {
                return [
                    'slug' => $skill->slug,
                    'name' => $skill->name,
                    'description' => $skill->description,
                    'readme_md' => $skill->readme_md,
                    'type' => $skill->type,
                    'status' => $skill->status,
                    'author' => $skill->author,
                    'tags' => $skill->tags,
                    'requires' => $skill->requires,
                    'versions' => $skill->versions->map(fn (SkillVersion $v) => [
                        'version' => $v->version,
                        'definition' => $v->definition,
                        'checksum' => $v->checksum,
                        'changelog' => $v->changelog,
                    ])->all(),
                ];
            })->all(),
        ];
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (! isset($data['records']) || ! is_array($data['records'])) {
            $errors[] = 'Brak wymaganej tablicy records w sekcji skills.';
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
            $existing = Skill::withTrashed()->where('slug', $item['slug'])->first();
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
                $existing = Skill::withTrashed()->where('slug', $item['slug'])->first();
                if ($existing) {
                    $existing->restore();
                    $existing->update([
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'readme_md' => $item['readme_md'] ?? null,
                        'type' => $item['type'] ?? 'tool',
                        'status' => $item['status'] ?? 'active',
                        'author' => $item['author'] ?? null,
                        'tags' => $item['tags'] ?? [],
                        'requires' => $item['requires'] ?? [],
                    ]);
                    $skill = $existing;
                } else {
                    $skill = Skill::create([
                        'slug' => $item['slug'],
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'readme_md' => $item['readme_md'] ?? null,
                        'type' => $item['type'] ?? 'tool',
                        'status' => $item['status'] ?? 'active',
                        'author' => $item['author'] ?? null,
                        'tags' => $item['tags'] ?? [],
                        'requires' => $item['requires'] ?? [],
                    ]);
                }

                foreach ($item['versions'] ?? [] as $ver) {
                    $vModel = SkillVersion::create([
                        'skill_id' => $skill->id,
                        'version' => $ver['version'],
                        'definition' => $ver['definition'] ?? null,
                        'checksum' => $ver['checksum'] ?? null,
                        'changelog' => $ver['changelog'] ?? null,
                    ]);
                    if (! $skill->current_version_id) {
                        $skill->update(['current_version_id' => $vModel->id]);
                    }
                }

                $importedCount++;
            }

            foreach ($plan['update'] ?? [] as $item) {
                $skill = Skill::withTrashed()->where('slug', $item['slug'])->first();
                if ($skill) {
                    $skill->restore();
                    $skill->update([
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'readme_md' => $item['readme_md'] ?? null,
                        'type' => $item['type'] ?? 'tool',
                        'status' => $item['status'] ?? 'active',
                        'author' => $item['author'] ?? null,
                        'tags' => $item['tags'] ?? [],
                        'requires' => $item['requires'] ?? [],
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
