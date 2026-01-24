<?php

use App\Http\Controllers\AddressImportController;
use App\Http\Controllers\CanvassingController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('canvassing.index');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Canvassing routes
    Route::get('/canvassing', [CanvassingController::class, 'index'])->name('canvassing.index');
    Route::get('/ward/{ward}', [CanvassingController::class, 'ward'])->name('canvassing.ward');
    Route::get('/ward/{ward}/street/{streetName}', [CanvassingController::class, 'street'])->name('canvassing.street');
    Route::post('/knock-result', [CanvassingController::class, 'store'])->name('knock-result.store');
    Route::put('/knock-result/{knockResult}', [CanvassingController::class, 'update'])->name('knock-result.update');
    Route::delete('/knock-result/{knockResult}', [CanvassingController::class, 'destroy'])->name('knock-result.destroy');

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

    // User management routes (admin only)
    Route::resource('users', UserController::class);
});

require __DIR__.'/auth.php';
