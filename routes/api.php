<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Api\McpServerApiController;
use App\Http\Controllers\Integrations\ExternalChannelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/conversations', [ConversationApiController::class, 'store'])
        ->middleware('permission:chat.group.create');

    Route::post('/conversations/{conversation}/participants', [ConversationApiController::class, 'addParticipant'])
        ->middleware('permission:chat.group.manage');

    Route::delete('/conversations/{conversation}/participants/{agent}', [ConversationApiController::class, 'removeParticipant'])
        ->middleware('permission:chat.group.manage');

    Route::post('/conversations/{conversation}/stop', [ConversationApiController::class, 'stop'])
        ->middleware('permission:chat.group.manage');

    // Wbudowany Serwer Model Context Protocol (MCP Hub v1.7.0)
    Route::get('/mcp/manifest', [McpServerApiController::class, 'manifest'])->name('api.v1.mcp.manifest');
    Route::post('/mcp/rpc', [McpServerApiController::class, 'handleRpc'])->name('api.v1.mcp.rpc');

    // Dwukierunkowe Kanały Zewnętrzne ChatOps & Human-in-the-Loop (v1.9.0)
    Route::post('/hooks/channels/{slug}', [ExternalChannelController::class, 'handleWebhook'])->name('api.v1.hooks.channels');
});
