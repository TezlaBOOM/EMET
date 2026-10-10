<?php

declare(strict_types=1);

use App\Http\Controllers\System\ConfigTransferController;
use App\Http\Controllers\System\FirstSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'permission:system.manage'])->prefix('system')->name('system.')->group(function () {
    // Transfery konfiguracji (Eksport / Import)
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/', [ConfigTransferController::class, 'index'])->name('index');
        Route::post('/export', [ConfigTransferController::class, 'export'])->name('export');
        Route::post('/dry-run', [ConfigTransferController::class, 'dryRun'])->name('dry-run');
        Route::post('/import', [ConfigTransferController::class, 'import'])->name('import');
    });

    // Instrukcja pierwszej konfiguracji (Etap 17 / v1.5.0)
    Route::prefix('first-setup')->name('first-setup.')->group(function () {
        Route::get('/', [FirstSetupController::class, 'index'])->name('index');
        Route::post('/toggle/{step}', [FirstSetupController::class, 'toggleStep'])->name('toggle');
    });
});
