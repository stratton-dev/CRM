<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\SupabaseSyncController;
use App\Http\Controllers\Api\Admin\StructureTeamsController;
use App\Http\Controllers\Api\ImapAdminController;

Route::post('admin/sync', SupabaseSyncController::class)->middleware('can:roles.delete');
Route::get('admin/teams', [StructureTeamsController::class, 'index'])->middleware('can:roles.delete');
Route::post('admin/teams', [StructureTeamsController::class, 'store'])->middleware('can:roles.delete');
Route::delete('admin/teams', [StructureTeamsController::class, 'destroy'])->middleware('can:roles.delete');

Route::get('admin/imap/health', [ImapAdminController::class, 'health'])->middleware('can:imap-admin.view');
Route::get('admin/imap/metrics', [ImapAdminController::class, 'metrics'])->middleware('can:imap-admin.view');
Route::get('admin/imap/logs', [ImapAdminController::class, 'logs'])->middleware('can:imap-admin.view');
Route::get('admin/imap/jobs', [ImapAdminController::class, 'jobs'])->middleware('can:imap-admin.view');
Route::get('admin/imap/stats', [ImapAdminController::class, 'stats'])->middleware('can:imap-admin.view');
