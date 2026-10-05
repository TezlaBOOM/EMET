<?php

declare(strict_types=1);

namespace App\Contracts\Telemetry;

use App\Models\TelemetryEvent;

interface TelemetryCollectorInterface
{
    /**
     * Rejestruje pojedyncze zdarzenie telemetryczne agenta lub runtime'u
     *
     * @param array<string, mixed> $payload
     */
    public function recordEvent(string $type, ?int $agentId = null, ?string $runId = null, array $payload = []): TelemetryEvent;

    /**
     * Zwraca podsumowanie aktywności agentów w czasie rzeczywistym
     *
     * @return array<string, mixed>
     */
    public function getActiveAgentsSummary(): array;

    /**
     * Zwraca zagregowane statystyki zużycia tokenów
     *
     * @return array<string, mixed>
     */
    public function getTokenUsageMetrics(string $period = '24h'): array;

    /**
     * Zwraca metryki wydajnościowe (TTFT, p50, p95 latencji, sukces vs błędy)
     *
     * @return array<string, mixed>
     */
    public function getPerformanceMetrics(string $period = '24h'): array;

    /**
     * Zwraca podsumowanie szacunkowych kosztów
     *
     * @return array<string, mixed>
     */
    public function getCostSummary(string $period = '24h'): array;

    /**
     * Zwraca zagregowany stan zdrowia integracji i instancji
     *
     * @return array<string, mixed>
     */
    public function getIntegrationsHealthSummary(): array;
}
