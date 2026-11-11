<?php

declare(strict_types=1);

namespace App\Contracts\Telemetry;

use App\Models\TelemetrySpan;

interface DistributedTracerInterface
{
    /**
     * Rozpoczyna nowy rozproszony ślad (Trace) i tworzy główny span (Root Span).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{trace_id: string, root_span_id: string, span: ?TelemetrySpan}
     */
    public function startTrace(string $rootName, string $serviceName = 'agenthub-core', array $attributes = []): array;

    /**
     * Rejestruje pojedynczy span w ramach istniejącego trace_id (z opcjonalnym parent_span_id).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordSpan(
        string $traceId,
        string $name,
        string $serviceName,
        int $durationMs,
        ?string $parentSpanId = null,
        string $status = 'OK',
        array $attributes = [],
        ?string $errorMessage = null,
    ): ?TelemetrySpan;

    /**
     * Zwraca pełny widok kaskadowy Waterfall dla wskazanego trace_id wraz z analizą wąskich gardeł i błędów.
     *
     * @return array{
     *     trace_id: string,
     *     total_duration_ms: int,
     *     spans_count: int,
     *     has_errors: bool,
     *     bottleneck_span: ?array<string, mixed>,
     *     root_cause_error: ?array<string, mixed>,
     *     spans: list<array<string, mixed>>
     * }
     */
    public function getWaterfallTrace(string $traceId): array;
}

