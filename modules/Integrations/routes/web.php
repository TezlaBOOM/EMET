<?php

use App\Http\Controllers\Integrations\ContainerIntegrationController;
use App\Http\Controllers\Integrations\ExternalChannelController;
use App\Http\Controllers\Integrations\IntegrationController;
use App\Http\Controllers\Integrations\McpHubController;
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

    // Kontenery Docker (v1.5.0)
    Route::prefix('containers')->name('integrations.containers.')->group(function () {
        Route::get('/', [ContainerIntegrationController::class, 'index'])->name('index');
        Route::post('/detect', [ContainerIntegrationController::class, 'detect'])->name('detect');
        Route::post('/{container}/adopt', [ContainerIntegrationController::class, 'adopt'])->name('adopt');
        Route::post('/{container}/release', [ContainerIntegrationController::class, 'release'])->name('release');
        Route::post('/{container}/configure', [ContainerIntegrationController::class, 'configure'])->name('configure');
        Route::post('/{container}/autoconfig', [ContainerIntegrationController::class, 'autoConfig'])->name('autoconfig');
        Route::post('/{container}/exec', [ContainerIntegrationController::class, 'exec'])->name('exec');
    });

    // Model Context Protocol Hub (MCP Client & Server v1.7.0)
    Route::prefix('mcp')->name('integrations.mcp.')->group(function () {
        Route::get('/', [McpHubController::class, 'index'])->name('index');
        Route::post('/', [McpHubController::class, 'store'])->name('store');
        Route::post('/{server}/sync', [McpHubController::class, 'sync'])->name('sync');
        Route::delete('/{server}', [McpHubController::class, 'destroy'])->name('destroy');
    });

    // Dwukierunkowe Kanały Zewnętrzne i Boty ChatOps (v1.9.0)
    Route::prefix('channels')->name('integrations.channels.')->group(function () {
        Route::get('/', [ExternalChannelController::class, 'index'])->name('index');
        Route::post('/', [ExternalChannelController::class, 'store'])->name('store');
        Route::delete('/{channel}', [ExternalChannelController::class, 'destroy'])->name('destroy');
    });
});
