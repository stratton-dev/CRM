<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('active');
            $table->boolean('pending_setup')->default(false)->after('enabled');
            $table->timestamp('last_synced_at')->nullable()->after('pending_setup');
            $table->text('sync_error')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'enabled',
                'pending_setup',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};
