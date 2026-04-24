<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dateTime('paused_at')->nullable()->after('valid_until');
            $table->dateTime('resume_at')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'resume_at']);
        });
    }
};
