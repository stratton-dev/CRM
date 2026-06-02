<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nadaje roli LEADOWIEC uprawnienie 'calculations.view' (read-only).
 * Endpoint /v1/calculations jest chroniony przez middleware can:calculations.view —
 * bez tego leadowiec dostaje 403. Scoping do własnych klientów (added_by_user_id)
 * jest w CalculationsController::index.
 */
return new class extends Migration
{
    private array $codes = ['calculations.view'];

    public function up(): void
    {
        $role = DB::table('roles')->where('code', 'LEADOWIEC')->first();
        if (!$role) {
            return;
        }

        foreach ($this->codes as $code) {
            $perm = DB::table('permissions')->where('code', $code)->first();
            if (!$perm) {
                continue;
            }
            $exists = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $perm->id)
                ->exists();
            if (!$exists) {
                DB::table('permission_role')->insert([
                    'role_id'       => $role->id,
                    'permission_id' => $perm->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('code', 'LEADOWIEC')->first();
        if (!$role) {
            return;
        }
        $permIds = DB::table('permissions')->whereIn('code', $this->codes)->pluck('id')->all();
        if ($permIds) {
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->whereIn('permission_id', $permIds)
                ->delete();
        }
    }
};
