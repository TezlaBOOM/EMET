<?php

declare(strict_types=1);

use App\Http\Controllers\Scenarios\ScenariosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('scenarios')->group(function () {
    Route::get('/', [ScenariosController::class, 'index'])->name('scenarios.index')->middleware('permission:scenarios.view');
    Route::get('/create', [ScenariosController::class, 'create'])->name('scenarios.create')->middleware('permission:scenarios.manage');
    Route::post('/', [ScenariosController::class, 'store'])->name('scenarios.store')->middleware('permission:scenarios.manage');

    Route::get('/{scenario}/editor', [ScenariosController::class, 'editor'])->name('scenarios.editor')->middleware('permission:scenarios.manage');
    Route::get('/{scenario}/edit', [ScenariosController::class, 'edit'])->name('scenarios.edit')->middleware('permission:scenarios.manage');
    Route::put('/{scenario}', [ScenariosController::class, 'update'])->name('scenarios.update')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/versions/{version}/restore', [ScenariosController::class, 'restoreVersion'])->name('scenarios.restore-version')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/lock', [ScenariosController::class, 'acquireLock'])->name('scenarios.lock')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/unlock', [ScenariosController::class, 'releaseLock'])->name('scenarios.unlock')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/save', [ScenariosController::class, 'saveGraph'])->name('scenarios.save')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/publish', [ScenariosController::class, 'publish'])->name('scenarios.publish')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/run', [ScenariosController::class, 'run'])->name('scenarios.run')->middleware('permission:scenarios.run');

    Route::get('/runs/{run}', [ScenariosController::class, 'runStatus'])->name('scenarios.runs.status')->middleware('permission:scenarios.view');
    Route::post('/runs/{run}/resume', [ScenariosController::class, 'resumeRun'])->name('scenarios.runs.resume')->middleware('permission:scenarios.manage');

    // Harmonogramy i timery (v1.5.7)
    Route::get('/{scenario}/triggers', [ScenariosController::class, 'triggers'])->name('scenarios.triggers.index')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/triggers', [ScenariosController::class, 'storeTrigger'])->name('scenarios.triggers.store')->middleware('permission:scenarios.manage');
    Route::put('/{scenario}/triggers/{trigger}', [ScenariosController::class, 'updateTrigger'])->name('scenarios.triggers.update')->middleware('permission:scenarios.manage');
    Route::delete('/{scenario}/triggers/{trigger}', [ScenariosController::class, 'destroyTrigger'])->name('scenarios.triggers.destroy')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/triggers/{trigger}/toggle', [ScenariosController::class, 'toggleTrigger'])->name('scenarios.triggers.toggle')->middleware('permission:scenarios.manage');
    Route::post('/{scenario}/triggers/{trigger}/run-now', [ScenariosController::class, 'runTriggerNow'])->name('scenarios.triggers.run-now')->middleware('permission:scenarios.manage');
    Route::post('/triggers/preview', [ScenariosController::class, 'previewSchedule'])->name('scenarios.triggers.preview')->middleware('permission:scenarios.manage');
});
