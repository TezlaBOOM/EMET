<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('hello')->group(function () {
    Route::get('/', function () {
        return view('module-hello::index');
    })->name('hello.index');
});
