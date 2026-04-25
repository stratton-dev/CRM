<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\SupabaseSyncController;
use App\Http\Controllers\Api\Admin\StructureTeamsController;
use App\Http\Controllers\Api\ImapAdminController;

Route::post('admin/sync', SupabaseSyncController::class);
Route::get('admin/teams', [StructureTeamsController::class, 'index']);
Route::post('admin/teams', [StructureTeamsController::class, 'store']);
Route::delete('admin/teams', [StructureTeamsController::class, 'destroy']);

Route::get('admin/imap/health', [ImapAdminController::class, 'health']);
Route::get('admin/imap/metrics', [ImapAdminController::class, 'metrics']);
Route::get('admin/imap/logs', [ImapAdminController::class, 'logs']);
Route::get('admin/imap/jobs', [ImapAdminController::class, 'jobs']);
Route::get('admin/imap/stats', [ImapAdminController::class, 'stats']);
