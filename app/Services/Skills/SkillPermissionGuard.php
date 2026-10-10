<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\Agent;
use App\Models\Skill;
use InvalidArgumentException;

class SkillPermissionGuard
{
    /**
     * Sprawdza czy skill może zostać przypisany lub wykonany przez danego agenta.
     *
     * @return array{allowed: bool, missing_capabilities: array<int, string>}
     */
    public function check(Skill $skill, Agent $agent): array
    {
        $requires = $skill->requires ?? [];
        if (! is_array($requires) || empty($requires)) {
            return ['allowed' => true, 'missing_capabilities' => []];
        }

        $missing = [];

        foreach ($requires as $requiredCapability) {
            $required = strtolower(trim((string) $requiredCapability));

            if ($required === 'internet') {
                if (! $agent->hasInternetAccess()) {
                    $missing[] = 'internet (wymaga internet_mode: allowlist lub open)';
                }
            } elseif ($required === 'open_internet') {
                if (! $agent->isOpenInternet()) {
                    $missing[] = 'open_internet (wymaga internet_mode: open)';
                }
            } elseif ($required === 'memory.read' || $required === 'memory.write') {
                if ($agent->memory_collection_id === null) {
                    $missing[] = "{$required} (wymaga przypiętej kolekcji pamięci)";
                }
            }
        }

        return [
            'allowed' => empty($missing),
            'missing_capabilities' => $missing,
        ];
    }

    /**
     * Wymusza sprawdzenie uprawnień i rzuca wyjątek w razie braku zgodności.
     */
    public function enforce(Skill $skill, Agent $agent): void
    {
        $result = $this->check($skill, $agent);
        if (! $result['allowed']) {
            $missingStr = implode(', ', $result['missing_capabilities']);
            throw new InvalidArgumentException(
                "Zdolności agenta {$agent->name} nie spełniają wymagań skilla {$skill->name}: {$missingStr}"
            );
        }
    }
}
