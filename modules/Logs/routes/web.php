<?php

use App\Contracts\Telemetry\DistributedTracerInterface;
use App\Models\AuditLog;
use App\Models\TelemetrySpan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('logs')->group(function () {
    Route::get('/', function () {
        $logs = AuditLog::with('user')->latest('id')->paginate(25);

        return view('module-logs::index', compact('logs'));
    })->name('logs.index')->middleware('permission:audit.view');

    Route::get('/agents', function () {
        return view('module-logs::agents');
    })->name('logs.agents')->middleware('permission:audit.view');

    Route::get('/ai', function () {
        return view('module-logs::ai');
    })->name('logs.ai')->middleware('permission:audit.view');

    Route::get('/system', function () {
        return view('module-logs::system');
    })->name('logs.system')->middleware('permission:audit.view');

    Route::get('/traces', function (Request $request, DistributedTracerInterface $tracer) {
        $recentTraceIds = TelemetrySpan::query()
            ->select('trace_id')
            ->distinct()
            ->latest('id')
            ->limit(20)
            ->pluck('trace_id')
            ->toArray();

        $selectedTraceId = (string) $request->query('trace_id', $recentTraceIds[0] ?? '');
        $waterfall = $selectedTraceId !== '' ? $tracer->getWaterfallTrace($selectedTraceId) : null;

        return view('module-logs::traces', [
            'recentTraceIds' => $recentTraceIds,
            'selectedTraceId' => $selectedTraceId,
            'waterfall' => $waterfall,
        ]);
    })->name('logs.traces')->middleware('permission:audit.view');
});
