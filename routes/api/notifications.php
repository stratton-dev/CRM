<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\NotificationsController;

// Specific routes must be registered before parameterized apiResource routes
Route::post('notifications/mark-all-read', [NotificationsController::class, 'markAllRead'])->middleware('can:notifications.update');
Route::post('notifications/batch', [NotificationsController::class, 'batchStore'])->middleware('can:notifications.create');
Route::get('notifications/sent', [NotificationsController::class, 'sent'])->middleware('can:notifications.view');

Route::apiResource('notifications', NotificationsController::class)->only(['index', 'show'])->middleware('can:notifications.view');
Route::apiResource('notifications', NotificationsController::class)->only(['store'])->middleware('can:notifications.create');
Route::match(['put', 'patch'], 'notifications/{notification}', [NotificationsController::class, 'update'])->middleware('can:notifications.update');
Route::apiResource('notifications', NotificationsController::class)->only(['destroy'])->middleware('can:notifications.delete');
