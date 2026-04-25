<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MeetingsController;
use App\Http\Controllers\Api\MeetingAnalysesController;

Route::apiResource('meetings', MeetingsController::class)->only(['index', 'show']);
Route::apiResource('meetings', MeetingsController::class)->only(['store'])->middleware('can:meetings.create');
Route::apiResource('meetings', MeetingsController::class)->only(['update'])->middleware('can:update,meeting');
Route::apiResource('meetings', MeetingsController::class)->only(['destroy'])->middleware('can:meetings.delete');

Route::apiResource('meeting-analyses', MeetingAnalysesController::class)->only(['index', 'show'])->middleware('can:meeting-analyses.view');
Route::apiResource('meeting-analyses', MeetingAnalysesController::class)->only(['store'])->middleware('can:meeting-analyses.create');
Route::apiResource('meeting-analyses', MeetingAnalysesController::class)->only(['update'])->middleware('can:meeting-analyses.update');
Route::apiResource('meeting-analyses', MeetingAnalysesController::class)->only(['destroy'])->middleware('can:meeting-analyses.delete');
