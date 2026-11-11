<?php

use App\Http\Controllers\Agents\AgentController;
use App\Http\Controllers\Agents\AgentCooperationController;
use App\Http\Controllers\Agents\AgentEvaluationController;
use App\Http\Controllers\Agents\FederatedSkillHubController;
use App\Http\Controllers\Agents\SkillsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('agents')->group(function () {
    Route::get('/', [AgentController::class, 'index'])->name('agents.index')->middleware('permission:agents.view');
    Route::get('/active', [AgentController::class, 'active'])->name('agents.active')->middleware('permission:agents.view');
    Route::get('/templates', [AgentController::class, 'templates'])->name('agents.templates')->middleware('permission:agents.view');

    // Kooperacja Wieloagentowa & Strażnik Przegrzania (v2.1.0)
    Route::get('/cooperation', [AgentCooperationController::class, 'index'])->name('agents.cooperation.index')->middleware('permission:agents.view');
    Route::post('/cooperation', [AgentCooperationController::class, 'store'])->name('agents.cooperation.store')->middleware('permission:agents.manage');
    Route::post('/cooperation/{cooperation}/run', [AgentCooperationController::class, 'runTask'])->name('agents.cooperation.run')->middleware('permission:agents.manage');
    Route::put('/cooperation/{cooperation}/guard', [AgentCooperationController::class, 'updateGuard'])->name('agents.cooperation.guard')->middleware('permission:agents.manage');
    Route::post('/cooperation/{cooperation}/cooldown', [AgentCooperationController::class, 'cooldown'])->name('agents.cooperation.cooldown')->middleware('permission:agents.manage');
    Route::delete('/cooperation/{cooperation}', [AgentCooperationController::class, 'destroy'])->name('agents.cooperation.destroy')->middleware('permission:agents.manage');

    // Ewaluacje i Benchmarki A/B (v1.8.0)
    Route::get('/evals', [AgentEvaluationController::class, 'index'])->name('agents.evals.index')->middleware('permission:agents.view');
    Route::post('/evals', [AgentEvaluationController::class, 'storeSuite'])->name('agents.evals.store')->middleware('permission:agents.manage');
    Route::post('/evals/{suite}/run', [AgentEvaluationController::class, 'runSuite'])->name('agents.evals.run')->middleware('permission:agents.manage');

    // Sfederowany Katalog Skilli i Szablonów Ed25519 (v1.9.0)
    Route::get('/hub', [FederatedSkillHubController::class, 'index'])->name('agents.hub.index')->middleware('permission:skills.view');
    Route::post('/hub/registries', [FederatedSkillHubController::class, 'storeRegistry'])->name('agents.hub.store')->middleware('permission:skills.manage');

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
    Route::post('/{agent}/skills', [AgentController::class, 'attachSkill'])->name('agents.skills.attach')->middleware('permission:agents.manage');
    Route::post('/{agent}/skills/{skill}/toggle', [AgentController::class, 'toggleSkill'])->name('agents.skills.toggle')->middleware('permission:agents.manage');
    Route::delete('/{agent}/skills/{skill}', [AgentController::class, 'detachSkill'])->name('agents.skills.detach')->middleware('permission:agents.manage');
});
