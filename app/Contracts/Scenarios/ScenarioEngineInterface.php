<?php

declare(strict_types=1);

namespace App\Contracts\Scenarios;

/**
 * Kontrakt silnika wykonawczego scenariuszy blokowych (Scenario Engine).
 */
interface ScenarioEngineInterface
{
    /**
     * Walidacja struktury grafu scenariusza (cykle, węzły start/end, zgodność portów).
     *
     * @param  array<string, mixed>  $graphJson
     * @return array{is_valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateGraph(array $graphJson): array;

    /**
     * Uruchomienie scenariusza na podstawie opublikowanej wersji z przekazanymi danymi wejściowymi.
     *
     * @param  array<string, mixed>  $input
     * @param  string  $triggerType  (manual, cron, webhook, time_travel_fork)
     * @return int Identyfikator rekordu scenario_run
     */
    public function startRun(int $scenarioId, ?int $versionId, array $input, string $triggerType = 'manual'): int;

    /**
     * Wykonanie pojedynczego węzła grafu w kolejce asynchronicznej (idempotentny krok).
     *
     * @return array{status: string, output: mixed, error: ?string}
     */
    public function executeNode(int $runId, string $nodeId): array;

    /**
     * Wstrzymanie (pauza) lub anulowanie bieżącego uruchomienia scenariusza.
     */
    public function pauseRun(int $runId): bool;

    /**
     * Wznowienie wstrzymanego scenariusza lub zatwierdzenie oczekującego kroku ludzkiego (human).
     *
     * @param  array<string, mixed>  $humanInput
     */
    public function resumeRun(int $runId, ?string $nodeId = null, array $humanInput = []): bool;

    /**
     * Zatrzymanie awaryjne uruchomienia scenariusza.
     */
    public function stopRun(int $runId): bool;

    /**
     * Time-Travel Debugger (v1.8.0): Rozwidla (forkuje) historyczny przebieg scenariusza od wskazanego węzła,
     * odtwarzając migawkę stanu zmiennych z momentu wejścia do węzła, nakładając modyfikacje zmiennych
     * i wznawiając wykonanie od tego kroku.
     *
     * @param  array<string, mixed>  $overrideVars
     * @return int Identyfikator nowego, rozwidlonego rekordu scenario_run
     */
    public function forkAndReplayFromStep(int $sourceRunId, string $fromNodeId, array $overrideVars = []): int;

    /**
     * Time-Travel Debugger (v1.8.0): Zwraca uporządkowaną oś czasu kroków uruchomienia wraz z migawkami stanu
     * przed/po każdym kroku oraz wyliczonym diffem zmiennych kontekstu.
     *
     * @return array{
     *     run_id: int,
     *     parent_run_id: ?int,
     *     forked_from_node_id: ?string,
     *     trace_id: ?string,
     *     status: string,
     *     timeline: list<array<string, mixed>>
     * }
     */
    public function inspectRunTimeline(int $runId): array;
}
