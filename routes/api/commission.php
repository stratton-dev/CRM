<?php

use App\Http\Controllers\Api\CommissionChainController;
use App\Http\Controllers\Api\CommissionController;
use Illuminate\Support\Facades\Route;

Route::post('commission/preview', [CommissionController::class, 'preview']);
Route::post('commission/distributions', [CommissionController::class, 'store']);
Route::get('commission/distributions', [CommissionController::class, 'index']);
Route::delete('commission/distributions/{distribution}', [CommissionController::class, 'destroy']);

Route::get('commission/my-settlements', [CommissionController::class, 'mySettlements']);

Route::get('users/{user}/commission-chain', [CommissionChainController::class, 'show']);
Route::put('users/{user}/commission-chain', [CommissionChainController::class, 'update']);
