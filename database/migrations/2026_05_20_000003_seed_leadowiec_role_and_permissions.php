<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $newPermissions = [
        ['code' => 'client-notes.view',          'description' => 'Przeglądanie notatek klientów'],
        ['code' => 'client-notes.create',        'description' => 'Dodawanie notatek do klientów'],
        ['code' => 'client-notes.update',        'description' => 'Edycja własnych notatek'],
        ['code' => 'client-notes.delete',        'description' => 'Usuwanie własnych notatek'],
        ['code' => 'leadowiec.opiekun.view',     'description' => 'Podgląd przypisanego opiekuna'],
        ['code' => 'leadowiec.settlements.view', 'description' => 'Podgląd rozliczeń i prowizji'],
    ];

    private array $leadowiecPermissions = [
        'clients.view',
        'clients.create',
        'clients.update',
        'client-contacts.view',
        'client-contacts.create',
        'client-contacts.update',
        'client-notes.view',
        'client-notes.create',
        'client-notes.update',
        'client-notes.delete',
        'meetings.view',
        'notifications.view',
        'crm-mail-settings.view',
        'crm-mail-settings.update',
        'crm-mailbox.view',
        'crm-mailbox.send',
        'crm-mailbox.update',
        'leadowiec.opiekun.view',
        'leadowiec.settlements.view',
    ];

    public function up(): void
    {
        // 1. Insert new permissions
        foreach ($this->newPermissions as $perm) {
            if (!DB::table('permissions')->where('code', $perm['code'])->exists()) {
                DB::table('permissions')->insert($perm);
            }
        }

        // 2. Create LEADOWIEC role
        if (!DB::table('roles')->where('code', 'LEADOWIEC')->exists()) {
            DB::table('roles')->insert([
                'code' => 'LEADOWIEC',
                'name' => 'Leadowiec',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $role = DB::table('roles')->where('code', 'LEADOWIEC')->first();
        if (!$role) {
            return;
        }

        // 3. Assign permissions to LEADOWIEC role
        foreach ($this->leadowiecPermissions as $code) {
            $permission = DB::table('permissions')->where('code', $code)->first();
            if (!$permission) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $permission->id)
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert([
                    'role_id'       => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('code', 'LEADOWIEC')->first();
        if ($role) {
            DB::table('permission_role')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        DB::table('permissions')
            ->whereIn('code', array_column($this->newPermissions, 'code'))
            ->delete();
    }
};
