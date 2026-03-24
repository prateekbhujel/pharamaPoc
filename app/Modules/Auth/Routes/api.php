<?php

use App\Modules\Auth\Http\Controllers\Api\V1\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('/api/v1/auth')
    ->name('auth.')
    ->group(function (): void {
        Route::post('/login', [SessionController::class, 'store'])->middleware('guest')->name('login');
        Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
        Route::get('/me', [SessionController::class, 'show'])->middleware('auth')->name('me');
    });
