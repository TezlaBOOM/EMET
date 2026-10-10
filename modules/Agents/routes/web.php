<?php

use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\SkillsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('agents')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agents.index')->middleware('permission:agents.view');
    Route::get('/active', [AgentController::class, 'active'])->name('agents.active')->middleware('permission:agents.view');
    Route::get('/templates', [AgentController::class, 'templates'])->name('agents.templates')->middleware('permission:agents.view');

    // Skille (v1.5.0)
    Route::get('/skills', [SkillsController::class, 'index'])->name('agents.skills.index')->middleware('permission:skills.view');
    Route::get('/skills/create', [SkillsController::class, 'create'])->name('agents.skills.create')->middleware('permission:skills.manage');
    Route::post('/skills', [SkillsController::class, 'store'])->name('agents.skills.store')->middleware('permission:skills.manage');
    Route::get('/skills/{skill}', [SkillsController::class, 'show'])->name('agents.skills.show')->middleware('permission:skills.view');
    Route::post('/skills/{skill}/test', [SkillsController::class, 'testSandbox'])->name('agents.skills.test')->middleware('permission:skills.manage');

    Route::get('/create', [AgentController::class, 'create'])->name('agents.create')->middleware('permission:agents.manage');
    Route::post('/', [AgentController::class, 'store'])->name('agents.store')->middleware('permission:agents.manage');
    Route::get('/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit')->middleware('permission:agents.manage');
    Route::put('/{agent}', [AgentController::class, 'update'])->name('agents.update')->middleware('permission:agents.manage');
    Route::delete('/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy')->middleware('permission:agents.manage');
    Route::post('/{agent}/skills/{skill}/toggle', [AgentController::class, 'toggleSkill'])->name('agents.skills.toggle')->middleware('permission:agents.manage');
});
