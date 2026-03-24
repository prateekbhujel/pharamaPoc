<?php

use App\Modules\Docs\Http\Controllers\Public\SwaggerController;
use Illuminate\Support\Facades\Route;

Route::get('/docs', fn () => redirect('/docs/swagger'));
Route::get('/docs/swagger', SwaggerController::class)->name('docs.swagger');
