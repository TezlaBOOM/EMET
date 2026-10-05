<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

// Trasy autentykacji
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Kreator pierwszego uruchomienia (Setup Wizard - Etap 8)
use App\Http\Controllers\Setup\SetupWizardController;

Route::middleware(['web', 'auth'])->prefix('setup')->group(function () {
    Route::get('/', function () {
        return redirect()->route('setup.step', ['step' => 1]);
    });
    Route::get('/step/{step}', [SetupWizardController::class, 'show'])->name('setup.step');
    Route::post('/step/{step}', [SetupWizardController::class, 'save'])->name('setup.save');
    Route::post('/step/{step}/skip', [SetupWizardController::class, 'skip'])->name('setup.skip');
    Route::post('/finish', [SetupWizardController::class, 'finish'])->name('setup.finish');
});

