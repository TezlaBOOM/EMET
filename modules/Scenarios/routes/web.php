<?php

declare(strict_types=1);

use App\Http\Controllers\Scenarios\ScenariosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('scenarios')->group(function () {
    Route::get('/', [ScenariosController::class, 'index'])->name('scenarios.index')->middleware('permission:scenarios.view');
    Route::get('/create', [ScenariosController::class, 'create'])->name('scenarios.create')->middleware('permission:scenarios.manage');
    Route::post('/', [ScenariosController::class, 'store'])->name('scenarios.store')->middleware('permission:scenarios.manage');

    Route::get('/{scenario}/editor', [ScenariosController::class, 'editor'])->name('scenarios.editor')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/save', [ScenariosController::class, 'saveGraph'])->name('scenarios.save')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/publish', [ScenariosController::class, 'publish'])->name('scenarios.publish')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/run', [ScenariosController::class, 'run'])->name('scenarios.run')->middleware('permission:scenarios.run');

    Route::get('/runs/{run}', [ScenariosController::class, 'runStatus'])->name('scenarios.runs.status')->middleware('permission:scenarios.view');
    Route::post('/runs/{run}/resume', [ScenariosController::class, 'resumeRun'])->name('scenarios.runs.resume')->middleware('permission:scenarios.manage');
});
