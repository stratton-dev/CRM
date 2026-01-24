<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('parent_keycloak_id')->nullable()->after('keycloak_id');
            $table->string('team_group_path')->nullable()->after('team_id');
            $table->string('role_cached')->nullable()->after('team_group_path');
            $table->string('hierarchical_code')->nullable()->after('hierarchical_id');

            $table->index('parent_keycloak_id');
            $table->index('team_group_path');
            $table->unique('hierarchical_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['hierarchical_code']);
            $table->dropIndex(['parent_keycloak_id']);
            $table->dropIndex(['team_group_path']);
            $table->dropColumn([
                'parent_keycloak_id',
                'team_group_path',
                'role_cached',
                'hierarchical_code',
            ]);
        });
    }
};
