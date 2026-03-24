<?php

use App\Modules\Pharmacy\Http\Controllers\Api\V1\PharmacyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('/api/v1/pharmacies')
    ->name('pharmacies.')
    ->group(function (): void {
        Route::get('', [PharmacyController::class, 'index'])->name('index');
        Route::get('/export', [PharmacyController::class, 'export'])->name('export');
        Route::post('', [PharmacyController::class, 'store'])->name('store');
        Route::put('/{pharmacyId}', [PharmacyController::class, 'update'])->name('update');
        Route::delete('/{pharmacyId}', [PharmacyController::class, 'destroy'])->name('destroy');
    });
