<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('keycloak_id', 'supabase_id');
            $table->renameColumn('parent_keycloak_id', 'parent_supabase_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['keycloak_username', 'last_synced_at', 'sync_error']);
        });

        Schema::table('autenti_documents', function (Blueprint $table) {
            $table->renameColumn('user_keycloak_id', 'user_supabase_id');
            $table->renameColumn('initiator_keycloak_id', 'initiator_supabase_id');
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->renameColumn('actor_keycloak_id', 'actor_supabase_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('supabase_id', 'keycloak_id');
            $table->renameColumn('parent_supabase_id', 'parent_keycloak_id');
            $table->string('keycloak_username')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
        });

        Schema::table('autenti_documents', function (Blueprint $table) {
            $table->renameColumn('user_supabase_id', 'user_keycloak_id');
            $table->renameColumn('initiator_supabase_id', 'initiator_keycloak_id');
        });

        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->renameColumn('actor_supabase_id', 'actor_keycloak_id');
        });
    }
};
