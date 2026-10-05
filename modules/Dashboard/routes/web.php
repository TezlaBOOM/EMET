<?php

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/tokens', [DashboardController::class, 'tokens'])->name('dashboard.tokens');
    Route::get('/performance', [DashboardController::class, 'performance'])->name('dashboard.performance');
    Route::get('/costs', [DashboardController::class, 'costs'])->name('dashboard.costs');
    Route::get('/api/metrics', [DashboardController::class, 'metricsJson'])->name('dashboard.metrics.api');
});
