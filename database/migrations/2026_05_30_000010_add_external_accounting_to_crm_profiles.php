<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('crm_client_profiles')) {
            return;
        }

        Schema::table('crm_client_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_client_profiles', 'has_external_accounting')) {
                // Default true — most clients use an external accounting office,
                // which means they get the 22% commission (20% Stratton + 2%
                // accounting). Setting to false switches them to the 20% rate
                // (Stratton only — client has in-house HR/accounting).
                $table->boolean('has_external_accounting')
                    ->default(true)
                    ->after('analysis_json');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_client_profiles')) {
            return;
        }

        Schema::table('crm_client_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('crm_client_profiles', 'has_external_accounting')) {
                $table->dropColumn('has_external_accounting');
            }
        });
    }
};
