<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_client_profiles', function (Blueprint $table) {
            $table->string('source')->nullable();
            $table->string('industry')->nullable();
            $table->string('company_size')->nullable();
            $table->string('contact_position')->nullable();
            $table->boolean('is_decision_maker')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('crm_client_profiles', function (Blueprint $table) {
            $table->dropColumn(['source', 'industry', 'company_size', 'contact_position', 'is_decision_maker']);
        });
    }
};
