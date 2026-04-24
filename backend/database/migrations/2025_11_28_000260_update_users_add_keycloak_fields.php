<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id')->nullable()->unique()->after('id');
            $table->string('keycloak_username')->nullable()->after('keycloak_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn(['keycloak_id', 'keycloak_username']);
        });
    }
};
