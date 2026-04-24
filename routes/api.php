<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\Api\OrganizationsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\PermissionsController;
use App\Http\Controllers\Api\CrmViewPermissionsController;
use App\Http\Controllers\Api\CrmAuditLogsController;
use App\Http\Controllers\Api\CrmEmailsController;
use App\Http\Controllers\Api\CrmMailSettingsController;
use App\Http\Controllers\Api\CrmMailboxController;
use App\Http\Controllers\Api\ImapServiceController;
use App\Http\Controllers\Api\ImapAdminController;
use App\Http\Controllers\Api\CrmKnowledgeFilesController;
use App\Http\Controllers\Api\CrmInvoicesController;
use App\Http\Controllers\Api\CrmCommissionConfigsController;
use App\Http\Controllers\Api\CrmTeamCommissionThresholdsController;
use App\Http\Controllers\Api\CrmEmployeesController;
use App\Http\Controllers\Api\CrmDashboardController;
use App\Http\Controllers\Api\CrmDashboardEventsController;
use App\Http\Controllers\Api\CrmDashboardNewsController;
use App\Http\Controllers\Api\CrmDashboardKpisController;
use App\Http\Controllers\Api\CrmDashboardCalculationsController;
use App\Http\Controllers\Api\CrmClientActivitiesController;
use App\Http\Controllers\Api\CrmClientProfilesController;
use App\Http\Controllers\Api\CrmSavedOffersController;
use App\Http\Controllers\Api\CrmStatusesController;
use App\Http\Controllers\Api\CrmEventsController;
use App\Http\Controllers\Api\CrmEventLogsController;
use App\Http\Controllers\Api\CrmBroadcastsController;
use App\Http\Controllers\Api\EmployeesController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\ClientContactsController;
use App\Http\Controllers\Api\ConsentsController;
use App\Http\Controllers\Api\ClientConsentsController;
use App\Http\Controllers\Api\MeetingsController;
use App\Http\Controllers\Api\MeetingAnalysesController;
use App\Http\Controllers\Api\OffersController;
use App\Http\Controllers\Api\OfferVerificationsController;
use App\Http\Controllers\Api\OfferItemsController;
use App\Http\Controllers\Api\PublicOffersController;
use App\Http\Controllers\Api\PayrollsController;
use App\Http\Controllers\Api\PayrollSpreadsheetController;
use App\Http\Controllers\Api\PayrollItemsController;
use App\Http\Controllers\Api\CalculationsController;
use App\Http\Controllers\Api\PayrollCalculationsController;
use App\Http\Controllers\Api\CalculatorConfigsController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\AutentiStatusController;
use App\Http\Controllers\Api\AutentiWebhookController;
use App\Http\Controllers\Api\AutentiDocumentsController;
use App\Http\Controllers\Api\DocumentTemplatesController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\GusController;
use App\Http\Controllers\Api\Admin\SupabaseSyncController;
use App\Http\Controllers\Api\Admin\StructureTeamsController;
use App\Http\Controllers\Api\StructureController;
use App\Http\Controllers\Api\StructureUsersController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\CandidatesController;
use App\Http\Controllers\Api\LeadsController;
use App\Http\Controllers\Api\AiController;

Route::get('offers/{token}', PublicOffersController::class);
Route::post('autenti/webhook', AutentiWebhookController::class);

Broadcast::routes(['middleware' => ['supabase']]);

Route::prefix('v1')->group(function () {
    Route::get('imap-service/configs', [ImapServiceController::class, 'configs']);
});

Route::prefix('v1')->middleware('supabase')->group(function () {
    Route::get('me', MeController::class);

    Route::get('structure', [StructureController::class, 'index']);

    // AI Integration
    Route::post('ai/generate-diagnosis', [AiController::class, 'generateDiagnosis']);

    Route::post('structure/move', [StructureController::class, 'move']);
    Route::post('structure/remove', [StructureController::class, 'remove']);
    Route::post('structure/restore', [StructureController::class, 'restore']);
    Route::post('structure/teams/restore', [StructureController::class, 'restoreTeam']);
    Route::post('structure/teams/delete', [StructureController::class, 'deleteRemovedTeamUsersFromDb']);
    Route::post('structure/regenerate-codes', [StructureController::class, 'regenerateCodes']);
    Route::post('admin/keycloak/sync', SupabaseSyncController::class);
    Route::get('admin/keycloak/teams', [StructureTeamsController::class, 'index']);
    Route::post('admin/keycloak/teams', [StructureTeamsController::class, 'store']);
    Route::delete('admin/keycloak/teams', [StructureTeamsController::class, 'destroy']);

    Route::post('users', [StructureUsersController::class, 'store']);
    Route::get('gus', [GusController::class, 'byNip']);
    Route::post('meetings/prospect', [MeetingsController::class, 'storeProspect']);
    Route::apiResource('users', UsersController::class)
        ->only(['index', 'show', 'update']);

    Route::apiResource('organizations', OrganizationsController::class)
        ->only(['index', 'show'])
        ->middleware('can:organizations.view');
    Route::apiResource('organizations', OrganizationsController::class)
        ->only(['store'])
        ->middleware('can:organizations.create');
    Route::apiResource('organizations', OrganizationsController::class)
        ->only(['update'])
        ->middleware('can:organizations.update');
    Route::apiResource('organizations', OrganizationsController::class)
        ->only(['destroy'])
        ->middleware('can:organizations.delete');

    Route::apiResource('roles', RolesController::class)
        ->only(['index', 'show'])
        ->middleware('can:roles.view');
    Route::apiResource('roles', RolesController::class)
        ->only(['store'])
        ->middleware('can:roles.create');
    Route::apiResource('roles', RolesController::class)
        ->only(['update'])
        ->middleware('can:roles.update');
    Route::apiResource('roles', RolesController::class)
        ->only(['destroy'])
        ->middleware('can:roles.delete');

    Route::apiResource('permissions', PermissionsController::class)
        ->only(['index', 'show'])
        ->middleware('can:permissions.view');
    Route::apiResource('permissions', PermissionsController::class)
        ->only(['store'])
        ->middleware('can:permissions.create');
    Route::apiResource('permissions', PermissionsController::class)
        ->only(['update'])
        ->middleware('can:permissions.update');
    Route::apiResource('permissions', PermissionsController::class)
        ->only(['destroy'])
        ->middleware('can:permissions.delete');

    Route::get('crm-view-permissions', [CrmViewPermissionsController::class, 'index']);
    Route::post('crm-view-permissions', [CrmViewPermissionsController::class, 'store']);

    Route::get('crm-dashboard', CrmDashboardController::class);
    Route::apiResource('crm-dashboard-events', CrmDashboardEventsController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('crm-dashboard-news', CrmDashboardNewsController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('crm-dashboard-kpis', CrmDashboardKpisController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::apiResource('crm-dashboard-calculations', CrmDashboardCalculationsController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::apiResource('crm-emails', CrmEmailsController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('crm-mail-settings', [CrmMailSettingsController::class, 'show']);
    Route::put('crm-mail-settings', [CrmMailSettingsController::class, 'update']);
    Route::get('crm-mailbox/messages', [CrmMailboxController::class, 'index']);
    Route::get('crm-mailbox/folders', [CrmMailboxController::class, 'folders']);
    Route::get('crm-mailbox/test', [CrmMailboxController::class, 'test']);
    Route::post('crm-mailbox/send', [CrmMailboxController::class, 'send']);
    Route::patch('crm-mailbox/messages/{messageId}', [CrmMailboxController::class, 'mark']);
    Route::get('admin/imap/health', [ImapAdminController::class, 'health']);
    Route::get('admin/imap/metrics', [ImapAdminController::class, 'metrics']);
    Route::get('admin/imap/logs', [ImapAdminController::class, 'logs']);
    Route::get('admin/imap/jobs', [ImapAdminController::class, 'jobs']);
    Route::get('admin/imap/stats', [ImapAdminController::class, 'stats']);
    Route::apiResource('crm-knowledge-files', CrmKnowledgeFilesController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('crm-knowledge-files/{crmKnowledgeFile}/download', [CrmKnowledgeFilesController::class, 'download']);
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

    // Leads Management
    Route::apiResource('leads', LeadsController::class);
    Route::post('leads/{lead}/convert', [LeadsController::class, 'convert']);

    // Candidates
    Route::apiResource('candidates', CandidatesController::class);

    // Companies and Employees
    Route::apiResource('companies', CompaniesController::class)
        ->only(['index', 'show'])
        ->middleware('can:companies.view');
    Route::apiResource('companies', CompaniesController::class)
        ->only(['store'])
        ->middleware('can:companies.create');
    Route::apiResource('companies', CompaniesController::class)
        ->only(['update'])
        ->middleware('can:companies.update');
    Route::apiResource('companies', CompaniesController::class)
        ->only(['destroy'])
        ->middleware('can:companies.delete');

    Route::apiResource('employees', EmployeesController::class)
        ->only(['index', 'show'])
        ->middleware('can:employees.view');
    Route::apiResource('employees', EmployeesController::class)
        ->only(['store'])
        ->middleware('can:employees.create');
    Route::apiResource('employees', EmployeesController::class)
        ->only(['update'])
        ->middleware('can:employees.update');
    Route::apiResource('employees', EmployeesController::class)
        ->only(['destroy'])
        ->middleware('can:employees.delete');

    // Clients and nested contacts/consents
    Route::get('clients/check-nip', [ClientsController::class, 'checkNip'])
        ->middleware('can:clients.view');
    Route::apiResource('clients', ClientsController::class)
        ->only(['index', 'show']);
    Route::apiResource('clients', ClientsController::class)
        ->only(['store'])
        ->middleware('can:clients.create');
    Route::apiResource('clients', ClientsController::class)
        ->only(['update'])
        ->middleware('can:clients.update');
    Route::apiResource('clients', ClientsController::class)
        ->only(['destroy'])
        ->middleware('can:clients.delete');
    Route::apiResource('clients.contacts', ClientContactsController::class)
        ->only(['index', 'show'])
        ->middleware('can:client-contacts.view')
        ->shallow()
        ->parameters(['contacts' => 'contact']);
    Route::apiResource('clients.contacts', ClientContactsController::class)
        ->only(['store'])
        ->middleware('can:client-contacts.create')
        ->shallow()
        ->parameters(['contacts' => 'contact']);
    Route::apiResource('clients.contacts', ClientContactsController::class)
        ->only(['update'])
        ->middleware('can:client-contacts.update')
        ->shallow()
        ->parameters(['contacts' => 'contact']);
    Route::apiResource('clients.contacts', ClientContactsController::class)
        ->only(['destroy'])
        ->middleware('can:client-contacts.delete')
        ->shallow()
        ->parameters(['contacts' => 'contact']);

    Route::apiResource('consents', ConsentsController::class)
        ->only(['index', 'show'])
        ->middleware('can:consents.view');
    Route::get('consents/{consent}/file', [ConsentsController::class, 'file'])
        ->name('consents.file')
        ->middleware('can:consents.view');
    Route::apiResource('consents', ConsentsController::class)
        ->only(['store'])
        ->middleware('can:consents.create');
    Route::apiResource('consents', ConsentsController::class)
        ->only(['update'])
        ->middleware('can:consents.update');
    Route::apiResource('consents', ConsentsController::class)
        ->only(['destroy'])
        ->middleware('can:consents.delete');
    // Client consents — explicit routes (avoids consents.show name collision with shallow apiResource)
    Route::get('clients/{client}/consents', [ClientConsentsController::class, 'index'])
        ->middleware('can:client-consents.view')
        ->name('client-consents.index');
    Route::get('client-consents/{clientConsent}', [ClientConsentsController::class, 'show'])
        ->middleware('can:client-consents.view')
        ->name('client-consents.show');
    Route::post('clients/{client}/consents', [ClientConsentsController::class, 'store'])
        ->middleware('can:client-consents.create')
        ->name('client-consents.store');
    Route::match(['put', 'patch'], 'client-consents/{clientConsent}', [ClientConsentsController::class, 'update'])
        ->middleware('can:client-consents.update')
        ->name('client-consents.update');
    Route::delete('client-consents/{clientConsent}', [ClientConsentsController::class, 'destroy'])
        ->middleware('can:client-consents.delete')
        ->name('client-consents.destroy');

    // Meetings and analysis
    Route::apiResource('meetings', MeetingsController::class)
        ->only(['index', 'show']);
    Route::apiResource('meetings', MeetingsController::class)
        ->only(['store'])
        ->middleware('can:meetings.create');
    Route::apiResource('meetings', MeetingsController::class)
        ->only(['update'])
        ->middleware('can:update,meeting');
    Route::apiResource('meetings', MeetingsController::class)
        ->only(['destroy'])
        ->middleware('can:meetings.delete');
    Route::apiResource('meeting-analyses', MeetingAnalysesController::class)
        ->only(['index', 'show'])
        ->middleware('can:meeting-analyses.view');
    Route::apiResource('meeting-analyses', MeetingAnalysesController::class)
        ->only(['store'])
        ->middleware('can:meeting-analyses.create');
    Route::apiResource('meeting-analyses', MeetingAnalysesController::class)
        ->only(['update'])
        ->middleware('can:meeting-analyses.update');
    Route::apiResource('meeting-analyses', MeetingAnalysesController::class)
        ->only(['destroy'])
        ->middleware('can:meeting-analyses.delete');

    // Offers and nested offer items
    Route::apiResource('offers', OffersController::class)
        ->only(['index', 'show'])
        ->middleware('can:offers.view');
    Route::apiResource('offers', OffersController::class)
        ->only(['store'])
        ->middleware('can:offers.create');
    Route::apiResource('offers', OffersController::class)
        ->only(['update'])
        ->middleware('can:offers.update');
    Route::apiResource('offers', OffersController::class)
        ->only(['destroy'])
        ->middleware('can:offers.delete');

    // Nested items under offers with shallow routes for item operations
    Route::apiResource('offers.items', OfferItemsController::class)
        ->only(['index', 'show'])
        ->middleware('can:offer-items.view')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('offers.items', OfferItemsController::class)
        ->only(['store'])
        ->middleware('can:offer-items.create')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('offers.items', OfferItemsController::class)
        ->only(['update'])
        ->middleware('can:offer-items.update')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('offers.items', OfferItemsController::class)
        ->only(['destroy'])
        ->middleware('can:offer-items.delete')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);

    Route::apiResource('offers.verifications', OfferVerificationsController::class)
        ->only(['index', 'show'])
        ->middleware('can:offer-verifications.view')
        ->shallow()
        ->parameters([
            'verifications' => 'verification',
        ]);
    Route::apiResource('offers.verifications', OfferVerificationsController::class)
        ->only(['store'])
        ->middleware('can:offer-verifications.create')
        ->shallow()
        ->parameters([
            'verifications' => 'verification',
        ]);
    Route::apiResource('offers.verifications', OfferVerificationsController::class)
        ->only(['update'])
        ->middleware('can:offer-verifications.update')
        ->shallow()
        ->parameters([
            'verifications' => 'verification',
        ]);
    Route::apiResource('offers.verifications', OfferVerificationsController::class)
        ->only(['destroy'])
        ->middleware('can:offer-verifications.delete')
        ->shallow()
        ->parameters([
            'verifications' => 'verification',
        ]);

    // Payroll Spreadsheets
    Route::post('payroll-spreadsheets', [PayrollSpreadsheetController::class, 'store']);
    Route::apiResource('payroll-spreadsheets', PayrollSpreadsheetController::class)->except(['store']);

    // Payrolls
    Route::apiResource('payrolls', PayrollsController::class)
        ->only(['index', 'show'])
        ->middleware('can:payrolls.view');
    Route::apiResource('payrolls', PayrollsController::class)
        ->only(['store'])
        ->middleware('can:payrolls.create');
    Route::apiResource('payrolls', PayrollsController::class)
        ->only(['update'])
        ->middleware('can:payrolls.update');
    Route::apiResource('payrolls', PayrollsController::class)
        ->only(['destroy'])
        ->middleware('can:payrolls.delete');
    Route::apiResource('payrolls.items', PayrollItemsController::class)
        ->only(['index', 'show'])
        ->middleware('can:payroll-items.view')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('payrolls.items', PayrollItemsController::class)
        ->only(['store'])
        ->middleware('can:payroll-items.create')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('payrolls.items', PayrollItemsController::class)
        ->only(['update'])
        ->middleware('can:payroll-items.update')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);
    Route::apiResource('payrolls.items', PayrollItemsController::class)
        ->only(['destroy'])
        ->middleware('can:payroll-items.delete')
        ->shallow()
        ->parameters([
            'items' => 'item',
        ]);

    // Calculations
    Route::apiResource('calculations', CalculationsController::class)
        ->only(['index', 'show'])
        ->middleware('can:calculations.view');
    Route::apiResource('calculations', CalculationsController::class)
        ->only(['store'])
        ->middleware('can:calculations.create');
    Route::apiResource('calculations', CalculationsController::class)
        ->only(['update'])
        ->middleware('can:calculations.update');
    Route::apiResource('calculations', CalculationsController::class)
        ->only(['destroy'])
        ->middleware('can:calculations.delete');

    // Payroll calculations
    Route::apiResource('payroll-calculations', PayrollCalculationsController::class)
        ->only(['index', 'show'])
        ->middleware('can:payroll-calculations.view');
    Route::apiResource('payroll-calculations', PayrollCalculationsController::class)
        ->only(['store'])
        ->middleware('can:payroll-calculations.create');
    Route::apiResource('payroll-calculations', PayrollCalculationsController::class)
        ->only(['update'])
        ->middleware('can:payroll-calculations.update');
    Route::apiResource('payroll-calculations', PayrollCalculationsController::class)
        ->only(['destroy'])
        ->middleware('can:payroll-calculations.delete');

    // Calculator configs
    Route::apiResource('calculator-configs', CalculatorConfigsController::class)
        ->only(['index', 'show'])
        ->middleware('can:calculator-configs.view');
    Route::apiResource('calculator-configs', CalculatorConfigsController::class)
        ->only(['store'])
        ->middleware('can:calculator-configs.create');
    Route::apiResource('calculator-configs', CalculatorConfigsController::class)
        ->only(['update'])
        ->middleware('can:calculator-configs.update');
    Route::apiResource('calculator-configs', CalculatorConfigsController::class)
        ->only(['destroy'])
        ->middleware('can:calculator-configs.delete');

    // Notifications, documents, metrics
    Route::apiResource('notifications', NotificationsController::class)
        ->only(['index', 'show'])
        ->middleware('can:notifications.view');
    Route::apiResource('notifications', NotificationsController::class)
        ->only(['store'])
        ->middleware('can:notifications.create');
    Route::match(['put', 'patch'], 'notifications/{notification}', [NotificationsController::class, 'update'])
        ->middleware('can:notifications.update');
    Route::post('notifications/mark-all-read', [NotificationsController::class, 'markAllRead'])
        ->middleware('can:notifications.update');
    Route::post('notifications/batch', [NotificationsController::class, 'batchStore'])
        ->middleware('can:notifications.create');
    Route::get('notifications/sent', [NotificationsController::class, 'sent'])
        ->middleware('can:notifications.view');

    Route::apiResource('notifications', NotificationsController::class)
        ->only(['destroy'])
        ->middleware('can:notifications.delete');
    Route::apiResource('documents', DocumentsController::class)
        ->only(['index', 'show'])
        ->middleware('can:documents.view');
    Route::apiResource('documents', DocumentsController::class)
        ->only(['store'])
        ->middleware('can:documents.create');
    Route::apiResource('documents', DocumentsController::class)
        ->only(['update'])
        ->middleware('can:documents.update');
    Route::post('documents/{document}/autenti/sync', [DocumentsController::class, 'syncAutenti'])
        ->middleware('can:documents.update');
    Route::apiResource('documents', DocumentsController::class)
        ->only(['destroy'])
        ->middleware('can:documents.delete');
    Route::apiResource('document-templates', DocumentTemplatesController::class)
        ->only(['index'])
        ->middleware('can:documents.view');
    Route::apiResource('document-templates', DocumentTemplatesController::class)
        ->only(['store'])
        ->middleware('can:documents.create');
    Route::apiResource('document-templates', DocumentTemplatesController::class)
        ->only(['update'])
        ->middleware('can:documents.update');
    Route::apiResource('document-templates', DocumentTemplatesController::class)
        ->only(['destroy'])
        ->middleware('can:documents.delete');
    Route::get('autenti/status', AutentiStatusController::class)
        ->middleware('can:documents.view');
    Route::get('document-templates/suggestions', [DocumentTemplatesController::class, 'suggestions'])
        ->middleware('can:documents.view');
    Route::get('document-templates/{documentTemplate}/download', [DocumentTemplatesController::class, 'download'])
        ->middleware('can:documents.view');
    Route::get('document-templates/{documentTemplate}/preview', [DocumentTemplatesController::class, 'preview'])
        ->middleware('can:documents.view');
    Route::apiResource('autenti-documents', AutentiDocumentsController::class)
        ->only(['index', 'show'])
        ->middleware('can:documents.view');
    Route::post('autenti-documents/{autentiDocument}/sync', [AutentiDocumentsController::class, 'sync'])
        ->middleware('can:documents.update');
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

    // Announcements
    Route::get('announcements', [\App\Http\Controllers\AnnouncementController::class, 'index']);
    Route::get('announcements/manage', [\App\Http\Controllers\AnnouncementController::class, 'manage']);
    Route::post('announcements', [\App\Http\Controllers\AnnouncementController::class, 'store']);
    Route::match(['put', 'patch'], 'announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'update']);
    Route::delete('announcements/{announcement}', [\App\Http\Controllers\AnnouncementController::class, 'destroy']);
});

Route::options('/{any}', function() {
    return response('OK', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token, Origin, Authorization');
})->where('any', '.*');

