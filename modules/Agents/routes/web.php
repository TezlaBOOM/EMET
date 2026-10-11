<?php

use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\SkillsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('agents')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agents.index')->middleware('permission:agents.view');
    Route::get('/active', [AgentController::class, 'active'])->name('agents.active')->middleware('permission:agents.view');
    Route::get('/templates', [AgentController::class, 'templates'])->name('agents.templates')->middleware('permission:agents.view');

    // Skille (v1.5.0 i v1.5.7)
    Route::get('/skills', [SkillsController::class, 'index'])->name('agents.skills.index')->middleware('permission:skills.view');
    Route::get('/skills/create', [SkillsController::class, 'create'])->name('agents.skills.create')->middleware('permission:skills.manage');
    Route::post('/skills', [SkillsController::class, 'store'])->name('agents.skills.store')->middleware('permission:skills.manage');
    Route::get('/skills/{skill}', [SkillsController::class, 'show'])->name('agents.skills.show')->middleware('permission:skills.view');
    Route::get('/skills/{skill}/edit', [SkillsController::class, 'edit'])->name('agents.skills.edit')->middleware('permission:skills.edit');
    Route::put('/skills/{skill}', [SkillsController::class, 'update'])->name('agents.skills.update')->middleware('permission:skills.edit');
    Route::delete('/skills/{skill}', [SkillsController::class, 'destroy'])->name('agents.skills.destroy')->middleware('permission:skills.delete');
    Route::post('/skills/{id}/restore', [SkillsController::class, 'restore'])->name('agents.skills.restore')->middleware('permission:skills.delete');
    Route::delete('/skills/{id}/purge', [SkillsController::class, 'purge'])->name('agents.skills.purge')->middleware('permission:skills.purge');
    Route::post('/skills/{skill}/test', [SkillsController::class, 'testSandbox'])->name('agents.skills.test')->middleware('permission:skills.manage');

    Route::get('/create', [AgentController::class, 'create'])->name('agents.create')->middleware('permission:agents.manage');
    Route::post('/', [AgentController::class, 'store'])->name('agents.store')->middleware('permission:agents.manage');
    Route::get('/{agent}/edit', [AgentController::class, 'edit'])->name('agents.edit')->middleware('permission:agents.manage');
    Route::put('/{agent}', [AgentController::class, 'update'])->name('agents.update')->middleware('permission:agents.manage');
    Route::delete('/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy')->middleware('permission:agents.manage');
    Route::post('/{agent}/skills/{skill}/toggle', [AgentController::class, 'toggleSkill'])->name('agents.skills.toggle')->middleware('permission:agents.manage');
});
