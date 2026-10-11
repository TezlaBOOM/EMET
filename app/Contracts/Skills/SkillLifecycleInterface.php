<?php

declare(strict_types=1);

namespace App\Contracts\Skills;

use App\Models\Skill;
use App\Models\SkillVersion;

/**
 * Kontrakt cyklu życia, edycji i usuwania skilli z kontrolą zależności (v1.5.7).
 */
interface SkillLifecycleInterface
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
    ): SkillVersion;

    /**
     * Zwraca listę podmiotów zależnych od danego skilla (agenci, scenariusze).
     *
     * @return array{agents: array<int, array{id: int, name: string}>, scenarios: array<int, array{id: int, name: string}>}
     */
    public function checkDependencies(Skill $skill): array;

    /**
     * Sprawdza, czy dany skill jest nieużywany (brak powiązań z agentami i scenariuszami).
     * Używane do oznaczania skilla czerwoną flagą w interfejsie użytkownika.
     */
    public function isUnused(Skill $skill): bool;

    /**
     * Bezpieczne usunięcie skilla (soft delete). Rzuca wyjątek lub zwraca false, jeśli skill jest używany.
     */
    public function safeDelete(Skill $skill, bool $forceDetach = false): bool;

    /**
     * Przywraca usunięty skill z kosza.
     */
    public function restoreFromTrash(int $skillId): Skill;

    /**
     * Trwałe usunięcie skilla i powiązanych plików paczki z dysku.
     */
    public function purgePermanently(int $skillId): bool;
}
