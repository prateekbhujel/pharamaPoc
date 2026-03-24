<?php

use App\Modules\Dashboard\Http\Controllers\Public\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('home');
Route::get('/{any}', DashboardController::class)
    ->where('any', '^(?!api|up|storage).*$')
    ->name('spa');
