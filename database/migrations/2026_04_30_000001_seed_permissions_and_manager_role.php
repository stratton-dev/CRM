<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * All permission codes defined in AppServiceProvider (except structure.* which are Gate::define with args).
     */
    private array $allPermissions = [
        'roles.view', 'roles.create', 'roles.update', 'roles.delete',
        'permissions.view', 'permissions.create', 'permissions.update', 'permissions.delete',
        'companies.view', 'companies.create', 'companies.update', 'companies.delete',
        'employees.view', 'employees.create', 'employees.update', 'employees.delete',
        'clients.view', 'clients.create', 'clients.update', 'clients.delete',
        'client-contacts.view', 'client-contacts.create', 'client-contacts.update', 'client-contacts.delete',
        'consents.view', 'consents.create', 'consents.update', 'consents.delete',
        'client-consents.view', 'client-consents.create', 'client-consents.update', 'client-consents.delete',
        'meetings.view', 'meetings.create', 'meetings.update', 'meetings.delete',
        'meeting-analyses.view', 'meeting-analyses.create', 'meeting-analyses.update', 'meeting-analyses.delete',
        'offers.view', 'offers.create', 'offers.update', 'offers.delete',
        'offer-items.view', 'offer-items.create', 'offer-items.update', 'offer-items.delete',
        'offer-verifications.view', 'offer-verifications.create', 'offer-verifications.update', 'offer-verifications.delete',
        'payrolls.view', 'payrolls.create', 'payrolls.update', 'payrolls.delete',
        'payroll-items.view', 'payroll-items.create', 'payroll-items.update', 'payroll-items.delete',
        'calculations.view', 'calculations.create', 'calculations.update', 'calculations.delete',
        'payroll-calculations.view', 'payroll-calculations.create', 'payroll-calculations.update', 'payroll-calculations.delete',
        'calculator-configs.view', 'calculator-configs.create', 'calculator-configs.update', 'calculator-configs.delete',
        'notifications.view', 'notifications.create', 'notifications.update', 'notifications.delete',
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
        'candidates.view', 'candidates.create', 'candidates.update', 'candidates.delete',
        'crm-dashboard-events.view', 'crm-dashboard-events.create', 'crm-dashboard-events.update', 'crm-dashboard-events.delete',
        'crm-dashboard-news.view', 'crm-dashboard-news.create', 'crm-dashboard-news.update', 'crm-dashboard-news.delete',
        'crm-mail-settings.view', 'crm-mail-settings.update',
        'crm-mailbox.view', 'crm-mailbox.send', 'crm-mailbox.update',
        'imap-admin.view',
        'crm-commission-config.view', 'crm-commission-config.update',
        'crm-team-commission-thresholds.view', 'crm-team-commission-thresholds.update',
        'documents.view', 'documents.create', 'documents.update', 'documents.delete',
        'metrics.view', 'metrics.create', 'metrics.update', 'metrics.delete',
    ];

    /**
     * Permissions to grant to MANAGER role.
     * ADMIN/DIRECTOR bypass all checks via Gate::before, so they don't need entries.
     */
    private array $managerPermissions = [
        // Employees
        'employees.view',
        // Clients & contacts
        'clients.view', 'clients.create', 'clients.update', 'clients.delete',
        'client-contacts.view', 'client-contacts.create', 'client-contacts.update', 'client-contacts.delete',
        'consents.view', 'consents.create', 'consents.update',
        'client-consents.view', 'client-consents.create', 'client-consents.update',
        // Meetings
        'meetings.view', 'meetings.create', 'meetings.update', 'meetings.delete',
        'meeting-analyses.view', 'meeting-analyses.create', 'meeting-analyses.update',
        // Offers
        'offers.view', 'offers.create', 'offers.update', 'offers.delete',
        'offer-items.view', 'offer-items.create', 'offer-items.update', 'offer-items.delete',
        'offer-verifications.view', 'offer-verifications.create',
        // Payrolls & calculations
        'payrolls.view', 'payrolls.create', 'payrolls.update',
        'payroll-items.view', 'payroll-items.create', 'payroll-items.update',
        'calculations.view', 'calculations.create', 'calculations.update', 'calculations.delete',
        'payroll-calculations.view', 'payroll-calculations.create', 'payroll-calculations.update',
        'calculator-configs.view',
        // Notifications
        'notifications.view', 'notifications.create', 'notifications.update',
        // Leads & candidates
        'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.convert',
        'candidates.view', 'candidates.create', 'candidates.update', 'candidates.delete',
        // CRM dashboard
        'crm-dashboard-events.view', 'crm-dashboard-events.create', 'crm-dashboard-events.update',
        'crm-dashboard-news.view',
        // Mail
        'crm-mail-settings.view', 'crm-mail-settings.update',
        'crm-mailbox.view', 'crm-mailbox.send', 'crm-mailbox.update',
        // Commission (view-only for manager)
        'crm-commission-config.view',
        'crm-team-commission-thresholds.view', 'crm-team-commission-thresholds.update',
        // Documents
        'documents.view', 'documents.create', 'documents.update',
        // Metrics
        'metrics.view',
    ];

    public function up(): void
    {
        // 1. Insert all permissions (skip existing)
        foreach ($this->allPermissions as $code) {
            if (!DB::table('permissions')->where('code', $code)->exists()) {
                DB::table('permissions')->insert([
                    'code' => $code,
                    'description' => $code,
                ]);
            }
        }

        // 2. Find MANAGER role
        $managerRole = DB::table('roles')->where('code', 'MANAGER')->first();
        if (!$managerRole) {
            return;
        }

        // 3. Assign manager permissions
        foreach ($this->managerPermissions as $code) {
            $permission = DB::table('permissions')->where('code', $code)->first();
            if (!$permission) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('role_id', $managerRole->id)
                ->where('permission_id', $permission->id)
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $managerRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $managerRole = DB::table('roles')->where('code', 'MANAGER')->first();
        if ($managerRole) {
            DB::table('permission_role')->where('role_id', $managerRole->id)->delete();
        }
        DB::table('permissions')->whereIn('code', $this->allPermissions)->delete();
    }
};
