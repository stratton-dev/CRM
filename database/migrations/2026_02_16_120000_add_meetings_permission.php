<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roles = ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES'];

        // Ensure the record exists in crm_view_permissions
        DB::table('crm_view_permissions')->updateOrInsert(
            ['view_key' => 'meetings'],
            [
                'roles' => json_encode($roles),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('crm_view_permissions')->where('view_key', 'meetings')->delete();
    }
};
