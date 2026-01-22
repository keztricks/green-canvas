<?php

use App\Http\Controllers\AddressImportController;
use App\Http\Controllers\CanvasserController;
use App\Http\Controllers\CanvassingController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CanvassingController::class, 'index'])->name('canvassing.index');

// Canvassing routes
Route::get('/street/{streetName}', [CanvassingController::class, 'street'])->name('canvassing.street');
Route::post('/knock-result', [CanvassingController::class, 'store'])->name('knock-result.store');

// Canvasser management routes
Route::get('/canvassers', [CanvasserController::class, 'index'])->name('canvassers.index');
Route::post('/canvassers', [CanvasserController::class, 'store'])->name('canvassers.store');
Route::delete('/canvassers/{canvasser}', [CanvasserController::class, 'destroy'])->name('canvassers.destroy');
Route::patch('/canvassers/{canvasser}/toggle', [CanvasserController::class, 'toggleActive'])->name('canvassers.toggle');

// Export routes
Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
Route::get('/exports/create', [ExportController::class, 'create'])->name('exports.create');
Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
Route::delete('/exports/{export}', [ExportController::class, 'destroy'])->name('exports.destroy');

// Import routes
Route::get('/import', [AddressImportController::class, 'index'])->name('import.index');
Route::post('/import', [AddressImportController::class, 'store'])->name('import.store');
Route::delete('/import/clear', [AddressImportController::class, 'clear'])->name('import.clear');
