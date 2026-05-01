<?php

use App\Http\Controllers\Api\PushTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('push-tokens')->controller(PushTokenController::class)->group(function () {
    Route::get('vapid-public-key', 'vapidPublicKey');
    Route::post('/', 'store');
    Route::delete('/', 'destroy');
});
