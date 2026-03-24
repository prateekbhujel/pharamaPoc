<?php

use App\Modules\Reporting\Http\Controllers\Api\V1\ReportingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('/api/v1/reporting')
    ->name('reporting.')
    ->group(function (): void {
        Route::get('/options', [ReportingController::class, 'options'])->name('options');
        Route::get('/preview', [ReportingController::class, 'preview'])->name('preview');
        Route::get('/export-direct', [ReportingController::class, 'directDownload'])->name('direct-download');
        Route::post('/exports', [ReportingController::class, 'store'])->name('exports.store');
        Route::get('/exports/{publicId}', [ReportingController::class, 'show'])->name('exports.show');
        Route::get('/exports/{publicId}/download', [ReportingController::class, 'download'])->name('exports.download');
    });
