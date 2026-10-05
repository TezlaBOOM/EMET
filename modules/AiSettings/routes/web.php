<?php

use App\Http\Controllers\AiSettings\AiSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'permission:ai_settings.manage'])->prefix('ai-settings')->group(function () {
    Route::get('/', [AiSettingsController::class, 'index'])->name('ai-settings.index');
    Route::get('/accounts', [AiSettingsController::class, 'accounts'])->name('ai-settings.accounts');
    Route::post('/accounts', [AiSettingsController::class, 'storeAccount'])->name('ai-settings.accounts.store');
    Route::delete('/accounts/{account}', [AiSettingsController::class, 'destroyAccount'])->name('ai-settings.accounts.destroy');
    Route::post('/accounts/{account}/test', [AiSettingsController::class, 'testConnection'])->name('ai-settings.accounts.test');
    Route::get('/pools', [AiSettingsController::class, 'pools'])->name('ai-settings.pools');
    Route::get('/limits', [AiSettingsController::class, 'limits'])->name('ai-settings.limits');
});
