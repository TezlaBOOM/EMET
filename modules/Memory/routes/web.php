<?php

use App\Http\Controllers\Memory\MemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('memory')->group(function () {
    Route::get('/', [MemoryController::class, 'index'])->name('memory.index')->middleware('permission:memory.view');
    Route::get('/graph', [MemoryController::class, 'graph'])->name('memory.graph')->middleware('permission:memory.view');
    Route::get('/graph/{collection}/data', [MemoryController::class, 'graphData'])->name('memory.graph.data')->middleware('permission:memory.view');
    Route::get('/search', [MemoryController::class, 'search'])->name('memory.search')->middleware('permission:memory.view');

    Route::get('/collections', [MemoryController::class, 'collections'])->name('memory.collections')->middleware('permission:memory.manage');
    Route::post('/collections', [MemoryController::class, 'storeCollection'])->name('memory.collections.store')->middleware('permission:memory.manage');
    Route::delete('/collections/{collection}', [MemoryController::class, 'destroyCollection'])->name('memory.collections.destroy')->middleware('permission:memory.manage');

    Route::post('/collections/{collection}/documents', [MemoryController::class, 'storeDocument'])->name('memory.documents.store')->middleware('permission:memory.manage');
    Route::delete('/chunks/{chunk}', [MemoryController::class, 'destroyChunk'])->name('memory.chunks.destroy')->middleware('permission:memory.manage');
});
