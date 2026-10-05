<?php

use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'permission:agents.chat'])->prefix('chat')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/history', [ChatController::class, 'history'])->name('chat.history');
    Route::post('/', [ChatController::class, 'store'])->name('chat.store');
    Route::post('/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.messages.send');
    Route::post('/{conversation}/stream', [ChatController::class, 'streamMessage'])->name('chat.messages.stream');
    Route::delete('/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
});
