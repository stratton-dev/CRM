<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_consents', function (Blueprint $table) {
            $table->timestamp('denied_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_consents', function (Blueprint $table) {
            $table->dropColumn('denied_at');
        });
    }
};
