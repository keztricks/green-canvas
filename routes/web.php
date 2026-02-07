<?php

use App\Http\Controllers\AddressImportController;
use App\Http\Controllers\CanvassingController;
use App\Http\Controllers\ElectionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FeatureFlagController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('canvassing.index');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Redirect old profile routes to settings
    Route::get('/profile', fn() => redirect()->route('settings.index'))->name('profile.edit');
    Route::patch('/profile', fn() => redirect()->route('settings.index'))->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings routes (combines profile and preferences)
    Route::get('/settings', [UserSettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/profile', [UserSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::patch('/settings/password', [UserSettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/export-schedules', [UserSettingsController::class, 'updateExportSchedules'])->name('settings.export-schedules.update');
    Route::delete('/settings', [UserSettingsController::class, 'destroy'])->name('settings.destroy');

    // Canvassing routes
    Route::get('/canvassing', [CanvassingController::class, 'index'])->name('canvassing.index');
    Route::get('/ward/{ward}', [CanvassingController::class, 'ward'])->name('canvassing.ward');
    Route::get('/ward/{ward}/all-streets', [CanvassingController::class, 'allStreets'])->name('canvassing.all-streets');
    Route::get('/ward/{ward}/street/{streetName}', [CanvassingController::class, 'street'])->name('canvassing.street');
    Route::post('/knock-result', [CanvassingController::class, 'store'])->name('knock-result.store');
    Route::put('/knock-result/{knockResult}', [CanvassingController::class, 'update'])->name('knock-result.update');
    Route::delete('/knock-result/{knockResult}', [CanvassingController::class, 'destroy'])->name('knock-result.destroy');
    Route::post('/address/{address}/do-not-knock', [CanvassingController::class, 'markDoNotKnock'])->name('address.mark-do-not-knock');
    Route::delete('/address/{address}/do-not-knock', [CanvassingController::class, 'clearDoNotKnock'])->name('address.clear-do-not-knock');
    Route::post('/address/create', [CanvassingController::class, 'storeAddress'])->name('address.store');

    // Export routes
    Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
    Route::get('/exports/create', [ExportController::class, 'create'])->name('exports.create');
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
    Route::delete('/exports/{export}', [ExportController::class, 'destroy'])->name('exports.destroy');

    // Admin-only routes
    // User management routes (admins and ward admins)
    Route::resource('users', UserController::class);

    Route::middleware('admin')->group(function () {
        // Import routes
        Route::get('/import', [AddressImportController::class, 'index'])->name('import.index');
        Route::post('/import', [AddressImportController::class, 'store'])->name('import.store');
        Route::delete('/import/clear', [AddressImportController::class, 'clear'])->name('import.clear');

        // Feature flag routes
        Route::get('/feature-flags', [FeatureFlagController::class, 'index'])->name('feature-flags.index');
        Route::patch('/feature-flags/{flag}/toggle', [FeatureFlagController::class, 'toggle'])->name('feature-flags.toggle');

        // Election management routes
        Route::get('/elections', [ElectionController::class, 'index'])->name('elections.index');
        Route::get('/elections/create', [ElectionController::class, 'create'])->name('elections.create');
        Route::post('/elections', [ElectionController::class, 'store'])->name('elections.store');
        Route::delete('/elections/{election}', [ElectionController::class, 'destroy'])->name('elections.destroy');
    });

    // Election voting toggle (available to all authenticated users)
    Route::post('/address/{address}/election/{election}/toggle', [ElectionController::class, 'toggleVoted'])->name('address.election.toggle');
});

require __DIR__.'/auth.php';
