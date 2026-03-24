<?php

use App\Modules\Sales\Http\Controllers\Api\V1\SalesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('/api/v1/sales')
    ->name('sales.')
    ->group(function (): void {
        Route::get('', [SalesController::class, 'index'])->name('index');
        Route::get('/export', [SalesController::class, 'export'])->name('export');
        Route::get('/template', [SalesController::class, 'template'])->name('template');
        Route::post('', [SalesController::class, 'store'])->name('store');
        Route::put('/{saleItemId}', [SalesController::class, 'update'])->name('update');
        Route::delete('/{saleItemId}', [SalesController::class, 'destroy'])->name('destroy');
    });
