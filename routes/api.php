<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StreetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Streets
Route::get('/streets', [StreetController::class, 'index']);
Route::post('/streets/{id}/assign', [StreetController::class, 'assign']);
Route::get('/streets/{id}/addresses', [AddressController::class, 'byStreet']);

// Addresses
Route::post('/addresses/{id}/visit', [AddressController::class, 'visit']);
Route::get('/addresses/search', [SearchController::class, 'search']);
