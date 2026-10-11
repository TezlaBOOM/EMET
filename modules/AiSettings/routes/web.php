<?php

use App\Http\Controllers\AiSettings\AiSettingsController;
use App\Http\Controllers\AiSettings\ModelsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'permission:ai_settings.manage'])->prefix('ai-settings')->group(function () {
    Route::get('/', [AiSettingsController::class, 'index'])->name('ai-settings.index');
    Route::put('/providers/{provider}', [AiSettingsController::class, 'updateProvider'])->name('ai-settings.providers.update');
    Route::get('/accounts', [AiSettingsController::class, 'accounts'])->name('ai-settings.accounts');
    Route::post('/accounts', [AiSettingsController::class, 'storeAccount'])->name('ai-settings.accounts.store');
    Route::delete('/accounts/{account}', [AiSettingsController::class, 'destroyAccount'])->name('ai-settings.accounts.destroy');
    Route::post('/accounts/{account}/test', [AiSettingsController::class, 'testConnection'])->name('ai-settings.accounts.test');
    Route::get('/pools', [AiSettingsController::class, 'pools'])->name('ai-settings.pools');
    Route::get('/limits', [AiSettingsController::class, 'limits'])->name('ai-settings.limits');

    // Rejestr i edycja modeli AI
    Route::get('/models', [ModelsController::class, 'index'])->name('ai-settings.models.index');
    Route::post('/models', [ModelsController::class, 'store'])->name('ai-settings.models.store');
    Route::put('/models/{model}', [ModelsController::class, 'update'])->name('ai-settings.models.update');
    Route::post('/models/{model}/reset', [ModelsController::class, 'reset'])->name('ai-settings.models.reset');
    Route::delete('/models/{model}', [ModelsController::class, 'destroy'])->name('ai-settings.models.destroy');
    Route::post('/providers/{provider}/sync-models', [ModelsController::class, 'sync'])->name('ai-settings.providers.sync-models');
});
