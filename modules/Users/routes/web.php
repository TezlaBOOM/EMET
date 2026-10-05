<?php

use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('users.index')->middleware('permission:users.manage');
    Route::post('/', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.manage');
    Route::put('/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.manage');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.manage');

    Route::get('/roles', [UserController::class, 'roles'])->name('users.roles')->middleware('permission:roles.manage');
    Route::get('/profile', [UserController::class, 'profile'])->name('users.profile');
    Route::put('/profile/update', [UserController::class, 'updateProfile'])->name('users.profile.update');
    Route::get('/security', [UserController::class, 'security'])->name('users.security');
    Route::get('/system', [UserController::class, 'system'])->name('users.system')->middleware('permission:users.manage');
    Route::post('/system/compat-check', [UserController::class, 'runCompatCheck'])->name('users.system.compat_check')->middleware('permission:users.manage');
});
