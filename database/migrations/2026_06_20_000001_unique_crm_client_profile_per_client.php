<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate profiles (one client must have exactly one CRM profile),
        // keeping the lowest id per client_id. Portable across Postgres + SQLite.
        DB::statement(
            'DELETE FROM crm_client_profiles WHERE id NOT IN '
            . '(SELECT min_id FROM (SELECT MIN(id) AS min_id FROM crm_client_profiles GROUP BY client_id) t)'
        );

        Schema::table('crm_client_profiles', function (Blueprint $table) {
            $table->unique('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_client_profiles', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
        });
    }
};
