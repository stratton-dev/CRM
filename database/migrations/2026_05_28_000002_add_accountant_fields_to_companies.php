<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'accountant_name')) {
                $table->string('accountant_name')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('companies', 'accountant_email')) {
                $table->string('accountant_email')->nullable()->after('accountant_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'accountant_email')) {
                $table->dropColumn('accountant_email');
            }
            if (Schema::hasColumn('companies', 'accountant_name')) {
                $table->dropColumn('accountant_name');
            }
        });
    }
};
