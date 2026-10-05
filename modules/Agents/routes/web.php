<?php

use App\Http\Controllers\Agents\AgentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('agents')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agents.index')->middleware('permission:agents.view');
    Route::get('/active', [AgentController::class, 'active'])->name('agents.active')->middleware('permission:agents.view');
    Route::get('/templates', [AgentController::class, 'templates'])->name('agents.templates')->middleware('permission:agents.view');
    Route::get('/create', [AgentController::class, 'create'])->name('agents.create')->middleware('permission:agents.manage');
    Route::post('/', [AgentController::class, 'store'])->name('agents.store')->middleware('permission:agents.manage');
    Route::get('/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit')->middleware('permission:agents.manage');
    Route::put('/{agent}', [AgentController::class, 'update'])->name('agents.update')->middleware('permission:agents.manage');
    Route::delete('/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy')->middleware('permission:agents.manage');
    Route::post('/{agent}/skills/{skill}/toggle', [AgentController::class, 'toggleSkill'])->name('agents.skills.toggle')->middleware('permission:agents.manage');
});
