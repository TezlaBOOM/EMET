<?php

declare(strict_types=1);

namespace App\Services\TelemetryCollector;

use App\Contracts\Telemetry\DistributedTracerInterface;
use App\Models\TelemetrySpan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DistributedTracerService implements DistributedTracerInterface
{
    public function startTrace(string $rootName, string $serviceName = 'agenthub-core', array $attributes = []): array
    {
        $traceId = bin2hex(random_bytes(16));
        $rootSpanId = bin2hex(random_bytes(8));

        $span = $this->recordSpan(
            traceId: $traceId,
            name: $rootName,
            serviceName: $serviceName,
            durationMs: 1,
            parentSpanId: null,
            status: 'OK',
            attributes: $attributes,
            forcedSpanId: $rootSpanId,
        );

        return [
            'trace_id' => $traceId,
            'root_span_id' => $rootSpanId,
            'span' => $span,
        ];
    }

    public function recordSpan(
        string $traceId,
        string $name,
        string $serviceName,
        int $durationMs,
        ?string $parentSpanId = null,
        string $status = 'OK',
        array $attributes = [],
        ?string $errorMessage = null,
        ?string $forcedSpanId = null,
    ): ?TelemetrySpan {
        if (! Schema::hasTable('telemetry_spans')) {
            return null;
        }

        $spanId = $forcedSpanId ?: bin2hex(random_bytes(8));
        $endUs = (int) round(microtime(true) * 1_000_000);
        $startUs = max(0, $endUs - ($durationMs * 1000));

        return TelemetrySpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $traceId,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'name' => $name,
            'service_name' => $serviceName,
            'span_kind' => $parentSpanId === null ? 'SERVER' : 'INTERNAL',
            'status' => strtoupper($status),
            'start_time_us' => $startUs,
            'end_time_us' => $endUs,
            'duration_ms' => max(0, $durationMs),
            'attributes' => $attributes,
            'error_message' => $errorMessage,
            'created_at' => now(),
        ]);
    }

    public function getWaterfallTrace(string $traceId): array
    {
        if (! Schema::hasTable('telemetry_spans')) {
            return [
                'trace_id' => $traceId,
                'total_duration_ms' => 0,
                'spans_count' => 0,
                'has_errors' => false,
                'bottleneck_span' => null,
                'root_cause_error' => null,
                'spans' => [],
            ];
        }

        $rawSpans = TelemetrySpan::where('trace_id', $traceId)
            ->orderBy('start_time_us')
            ->get();

        if ($rawSpans->isEmpty()) {
            return [
                'trace_id' => $traceId,
                'total_duration_ms' => 0,
                'spans_count' => 0,
                'has_errors' => false,
                'bottleneck_span' => null,
                'root_cause_error' => null,
                'spans' => [],
            ];
        }

        $minStartUs = (int) $rawSpans->min('start_time_us');
        $maxEndUs = (int) $rawSpans->max('end_time_us');
        $sumDurations = (int) $rawSpans->sum('duration_ms');
        $totalDurationMs = max(1, (int) round(($maxEndUs - $minStartUs) / 1000), $sumDurations);

        $spansList = [];
        $bottleneck = null;
        $rootCauseError = null;
        $hasErrors = false;

        foreach ($rawSpans as $span) {
            $offsetMs = max(0, (int) round(($span->start_time_us - $minStartUs) / 1000));
            $sharePercent = round(min(100.0, ($span->duration_ms / $totalDurationMs) * 100), 1);

            $formatted = [
                'span_id' => $span->span_id,
                'parent_span_id' => $span->parent_span_id,
                'name' => $span->name,
                'service_name' => $span->service_name,
                'status' => $span->status,
                'duration_ms' => $span->duration_ms,
                'offset_ms' => $offsetMs,
                'share_percent' => $sharePercent,
                'attributes' => $span->attributes ?? [],
                'error_message' => $span->error_message,
            ];

            if ($bottleneck === null || $span->duration_ms > $bottleneck['duration_ms']) {
                $bottleneck = $formatted;
            }

            if ($span->status === 'ERROR') {
                $hasErrors = true;
                if ($rootCauseError === null) {
                    $rootCauseError = $formatted;
                }
            }

            $formatted['offset_pct'] = round(min(95.0, ($offsetMs / max(1, $totalDurationMs)) * 100), 1);
            $formatted['width_pct'] = $sharePercent;

            $spansList[] = $formatted;
        }

        return [
            'trace_id' => $traceId,
            'total_duration_ms' => $totalDurationMs,
            'spans_count' => count($spansList),
            'span_count' => count($spansList),
            'has_errors' => $hasErrors,
            'bottleneck_span' => $bottleneck,
            'root_cause_error' => $rootCauseError,
            'spans' => $spansList,
        ];
    }
}
