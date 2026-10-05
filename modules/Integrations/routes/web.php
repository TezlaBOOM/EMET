<?php

use App\Http\Controllers\Integrations\IntegrationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'permission:integrations.manage'])->prefix('integrations')->group(function () {
    Route::get('/', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/hermes', [IntegrationController::class, 'hermes'])->name('integrations.hermes');
    Route::get('/openclaw', [IntegrationController::class, 'openclaw'])->name('integrations.openclaw');
    Route::get('/claude', [IntegrationController::class, 'claude'])->name('integrations.claude');
    Route::get('/codex', [IntegrationController::class, 'codex'])->name('integrations.codex');

    Route::post('/', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::post('/reconcile', [IntegrationController::class, 'reconcile'])->name('integrations.reconcile');
    Route::delete('/{instance}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
});
