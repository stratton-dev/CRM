<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $codes = [
        'client-notes.view',
        'client-notes.create',
        'client-notes.update',
        'client-notes.delete',
    ];

    public function up(): void
    {
        // Make sure the permission rows exist (created earlier in 2026_05_20_000003,
        // but seed defensively in case that migration was skipped on a branch).
        foreach ($this->codes as $code) {
            if (!DB::table('permissions')->where('code', $code)->exists()) {
                DB::table('permissions')->insert([
                    'code' => $code,
                    'description' => $code,
                ]);
            }
        }

        $salesRole = DB::table('roles')->where('code', 'SALES')->first();
        if (!$salesRole) {
            return;
        }

        foreach ($this->codes as $code) {
            $permission = DB::table('permissions')->where('code', $code)->first();
            if (!$permission) {
                continue;
            }

            $exists = DB::table('permission_role')
                ->where('role_id', $salesRole->id)
                ->where('permission_id', $permission->id)
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $salesRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $salesRole = DB::table('roles')->where('code', 'SALES')->first();
        if (!$salesRole) {
            return;
        }
        $permissionIds = DB::table('permissions')->whereIn('code', $this->codes)->pluck('id');
        DB::table('permission_role')
            ->where('role_id', $salesRole->id)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
