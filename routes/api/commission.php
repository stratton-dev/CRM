<?php

use App\Http\Controllers\Api\CommissionController;
use Illuminate\Support\Facades\Route;

Route::post('commission/preview', [CommissionController::class, 'preview']);
Route::post('commission/distributions', [CommissionController::class, 'store']);
Route::get('commission/distributions', [CommissionController::class, 'index']);
Route::delete('commission/distributions/{distribution}', [CommissionController::class, 'destroy']);
