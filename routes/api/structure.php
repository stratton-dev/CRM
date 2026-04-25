<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StructureController;
use App\Http\Controllers\Api\StructureUsersController;
use App\Http\Controllers\Api\AiController;

Route::get('structure', [StructureController::class, 'index']);
Route::post('structure/move', [StructureController::class, 'move']);
Route::post('structure/remove', [StructureController::class, 'remove']);
Route::post('structure/restore', [StructureController::class, 'restore']);
Route::post('structure/teams/restore', [StructureController::class, 'restoreTeam']);
Route::post('structure/teams/delete', [StructureController::class, 'deleteRemovedTeamUsersFromDb']);
Route::post('structure/regenerate-codes', [StructureController::class, 'regenerateCodes']);

Route::post('users', [StructureUsersController::class, 'store']);

Route::post('ai/generate-diagnosis', [AiController::class, 'generateDiagnosis']);
