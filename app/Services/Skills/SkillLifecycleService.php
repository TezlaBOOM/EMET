<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Contracts\Skills\SkillLifecycleInterface;
use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\Scenario;
use App\Models\Skill;
use App\Models\SkillVersion;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SkillLifecycleService implements SkillLifecycleInterface
{
    /**
     * Aktualizuje definicję skilla, tworząc nową wersję SemVer.
     *
     * @param  array<string, mixed>  $definition
     */
    public function updateDefinitionWithNewVersion(
        Skill $skill,
        array $definition,
        string $changelog,
        string $bumpType = 'patch'
    ): SkillVersion {
        $latestVersion = $skill->currentVersion?->version ?? '1.0.0';
        $newVersionString = $this->bumpSemver($latestVersion, $bumpType);

        $newVersion = SkillVersion::create([
            'skill_id' => $skill->id,
            'version' => $newVersionString,
            'definition' => $definition,
            'changelog' => $changelog,
            'created_by' => auth()->id(),
        ]);

        $skill->current_version_id = $newVersion->id;
        $skill->save();

        return $newVersion;
    }

    /**
     * Zwraca listę podmiotów zależnych od danego skilla (agenci, scenariusze).
     *
     * @return array{agents: array<int, array{id: int, name: string}>, scenarios: array<int, array{id: int, name: string}>}
     */
    public function checkDependencies(Skill $skill): array
    {
        // 1. Zależności od agentów (powiązanie przez agent_skills z ID lub driver/slug)
        $agentIdsFromPivot = AgentSkill::where('skill_id', $skill->id)
            ->orWhere('driver', $skill->slug)
            ->pluck('agent_id')
            ->unique();

        $agents = Agent::whereIn('id', $agentIdsFromPivot)
            ->get(['id', 'name'])
            ->map(fn (Agent $a) => ['id' => $a->id, 'name' => $a->name])
            ->values()
            ->all();

        // 2. Zależności od scenariuszy (węzły grafu używające danego skilla)
        $scenarios = [];
        $allScenarios = Scenario::all();
        foreach ($allScenarios as $scenario) {
            $nodes = $scenario->graph['nodes'] ?? $scenario->draft_graph['nodes'] ?? [];
            foreach ($nodes as $node) {
                $nodeSkill = $node['config']['skill'] ?? $node['config']['skill_slug'] ?? $node['config']['skill_id'] ?? null;
                if ($nodeSkill === $skill->slug || $nodeSkill === (string) $skill->id || $nodeSkill === $skill->id) {
                    $scenarios[] = ['id' => $scenario->id, 'name' => $scenario->name];
                    break;
                }
            }
        }

        return [
            'agents' => $agents,
            'scenarios' => $scenarios,
        ];
    }

    /**
     * Sprawdza, czy dany skill jest nieużywany (brak powiązań z agentami i scenariuszami).
     * Używane do oznaczania skilla czerwoną flagą w interfejsie użytkownika.
     */
    public function isUnused(Skill $skill): bool
    {
        $deps = $this->checkDependencies($skill);

        return empty($deps['agents']) && empty($deps['scenarios']);
    }

    /**
     * Bezpieczne usunięcie skilla (soft delete). Rzuca wyjątek lub zwraca false, jeśli skill jest używany.
     */
    public function safeDelete(Skill $skill, bool $forceDetach = false): bool
    {
        $deps = $this->checkDependencies($skill);
        $hasDeps = ! empty($deps['agents']) || ! empty($deps['scenarios']);

        if ($hasDeps && ! $forceDetach) {
            throw new RuntimeException('Nie można usunąć skilla, który posiada aktywne powiązania z agentami lub scenariuszami.');
        }

        if ($hasDeps && $forceDetach) {
            AgentSkill::where('skill_id', $skill->id)->orWhere('driver', $skill->slug)->delete();
        }

        $skill->deleted_by = auth()->id();
        $skill->save();

        return (bool) $skill->delete();
    }

    /**
     * Przywraca usunięty skill z kosza.
     */
    public function restoreFromTrash(int $skillId): Skill
    {
        $skill = Skill::onlyTrashed()->findOrFail($skillId);
        $skill->restore();
        $skill->deleted_by = null;
        $skill->save();

        return $skill;
    }

    /**
     * Trwałe usunięcie skilla i powiązanych plików paczki z dysku.
     */
    public function purgePermanently(int $skillId): bool
    {
        $skill = Skill::withTrashed()->findOrFail($skillId);

        // Usunięcie powiązań
        AgentSkill::where('skill_id', $skill->id)->orWhere('driver', $skill->slug)->delete();

        // Usunięcie plików paczek z wersji
        foreach ($skill->versions as $version) {
            if ($version->package_path && File::exists($version->package_path)) {
                File::delete($version->package_path);
            }
            $version->delete();
        }

        $skill->runs()->delete();

        return (bool) $skill->forceDelete();
    }

    /**
     * Proste podbijanie wersji SemVer (major.minor.patch)
     */
    private function bumpSemver(string $version, string $type): string
    {
        $parts = explode('.', $version);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        return match ($type) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }
}
