<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CrmDashboardController;
use App\Http\Controllers\Api\CrmDashboardEventsController;
use App\Http\Controllers\Api\CrmDashboardNewsController;
use App\Http\Controllers\Api\CrmDashboardKpisController;
use App\Http\Controllers\Api\CrmDashboardCalculationsController;
use App\Http\Controllers\Api\CrmAuditLogsController;
use App\Http\Controllers\Api\CrmEmailsController;
use App\Http\Controllers\Api\CrmMailSettingsController;
use App\Http\Controllers\Api\CrmMailboxController;
use App\Http\Controllers\Api\CrmKnowledgeFilesController;
use App\Http\Controllers\Api\CrmInvoicesController;
use App\Http\Controllers\Api\CrmCommissionConfigsController;
use App\Http\Controllers\Api\CrmTeamCommissionThresholdsController;
use App\Http\Controllers\Api\CrmEmployeesController;
use App\Http\Controllers\Api\CrmClientActivitiesController;
use App\Http\Controllers\Api\CrmClientProfilesController;
use App\Http\Controllers\Api\CrmSavedOffersController;
use App\Http\Controllers\Api\CrmStatusesController;
use App\Http\Controllers\Api\CrmEventsController;
use App\Http\Controllers\Api\CrmEventLogsController;
use App\Http\Controllers\Api\CrmBroadcastsController;
use App\Http\Controllers\Api\MetricsController;

Route::get('crm-dashboard', CrmDashboardController::class);
Route::apiResource('crm-dashboard-events', CrmDashboardEventsController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-dashboard-news', CrmDashboardNewsController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-dashboard-kpis', CrmDashboardKpisController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-dashboard-calculations', CrmDashboardCalculationsController::class)->only(['index', 'store', 'update', 'destroy']);

Route::apiResource('crm-audit-logs', CrmAuditLogsController::class)->only(['index', 'store']);
Route::apiResource('crm-invoices', CrmInvoicesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::get('crm-commission-config', [CrmCommissionConfigsController::class, 'show']);
Route::put('crm-commission-config', [CrmCommissionConfigsController::class, 'update']);
Route::get('crm-team-commission-thresholds', [CrmTeamCommissionThresholdsController::class, 'index']);
Route::put('crm-team-commission-thresholds', [CrmTeamCommissionThresholdsController::class, 'upsert']);
Route::apiResource('crm-employees', CrmEmployeesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::apiResource('crm-client-activities', CrmClientActivitiesController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-client-profiles', CrmClientProfilesController::class)->only(['index', 'show', 'store', 'update']);
Route::apiResource('crm-saved-offers', CrmSavedOffersController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-statuses', CrmStatusesController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-events', CrmEventsController::class)->only(['index', 'store', 'update', 'destroy']);
Route::apiResource('crm-event-logs', CrmEventLogsController::class)->only(['index', 'store']);
Route::apiResource('crm-broadcasts', CrmBroadcastsController::class)->only(['index', 'store', 'update', 'destroy']);

Route::apiResource('crm-emails', CrmEmailsController::class)->only(['index', 'store', 'update', 'destroy']);
Route::get('crm-mail-settings', [CrmMailSettingsController::class, 'show']);
Route::put('crm-mail-settings', [CrmMailSettingsController::class, 'update']);
Route::get('crm-mailbox/messages', [CrmMailboxController::class, 'index']);
Route::get('crm-mailbox/folders', [CrmMailboxController::class, 'folders']);
Route::get('crm-mailbox/test', [CrmMailboxController::class, 'test']);
Route::post('crm-mailbox/send', [CrmMailboxController::class, 'send']);
Route::patch('crm-mailbox/messages/{messageId}', [CrmMailboxController::class, 'mark']);
Route::apiResource('crm-knowledge-files', CrmKnowledgeFilesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
Route::get('crm-knowledge-files/{crmKnowledgeFile}/download', [CrmKnowledgeFilesController::class, 'download']);

Route::apiResource('metrics', MetricsController::class)
    ->only(['index', 'show'])
    ->middleware('can:metrics.view');
Route::apiResource('metrics', MetricsController::class)
    ->only(['store'])
    ->middleware('can:metrics.create');
Route::apiResource('metrics', MetricsController::class)
    ->only(['update'])
    ->middleware('can:metrics.update');
Route::apiResource('metrics', MetricsController::class)
    ->only(['destroy'])
    ->middleware('can:metrics.delete');
