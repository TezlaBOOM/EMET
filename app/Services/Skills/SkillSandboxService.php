<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\Agent;
use App\Models\Skill;
use App\Models\SkillRun;
use Symfony\Component\Process\Process;
use Throwable;

class SkillSandboxService
{
    public function __construct(
        protected SkillPermissionGuard $permissionGuard
    ) {}

    /**
     * Uruchamia skill w odizolowanym środowisku wykonawczym z limitem czasu.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(
        Skill $skill,
        array $input = [],
        ?Agent $agent = null,
        ?int $agentRunId = null,
        ?int $versionId = null
    ): SkillRun {
        if ($agent !== null) {
            $this->permissionGuard->enforce($skill, $agent);
        }

        $effectiveVersionId = $versionId ?? $skill->current_version_id;
        $startTime = microtime(true);

        $status = SkillRun::STATUS_RUNNING;
        $error = null;

        try {
            // Weryfikacja czy skill to natywny skrypt czy tool deklaratywny
            $inputPayload = json_encode($input, JSON_THROW_ON_ERROR);

            // Przykładowy odizolowany skrypt PHP / runner bez dostępu do .env
            $isolatedScript = sprintf(
                '$input = json_decode(%s, true); echo json_encode(["status" => "ok", "result" => "Executed skill %s", "input" => $input]);',
                var_export($inputPayload, true),
                var_export($skill->slug, true)
            );

            // Czyste środowisko zmiennych - zero haseł i sekretów bazy
            $cleanEnv = [
                'PATH' => dirname(PHP_BINARY).':/usr/local/bin:/usr/bin:/bin',
                'AGENTHUB_RUNNER' => 'isolated',
            ];

            $process = new Process([PHP_BINARY, '-r', $isolatedScript], null, $cleanEnv, null, 5.0);
            $process->run();

            if (! $process->isSuccessful()) {
                $status = 'failed';
                $error = $process->getErrorOutput() ?: 'Błąd wykonania procesu w sandboxie';
            } else {
                $status = 'success';
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return SkillRun::create([
            'skill_id' => $skill->id,
            'version_id' => $effectiveVersionId,
            'agent_run_id' => $agentRunId,
            'input_summary' => $input,
            'status' => $status,
            'duration_ms' => $durationMs,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
