<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ConversationApiController;
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
});
