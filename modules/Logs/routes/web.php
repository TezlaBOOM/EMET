<?php

use App\Models\AuditLog;
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
});
