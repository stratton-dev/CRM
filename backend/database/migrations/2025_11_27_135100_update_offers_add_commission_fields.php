<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Prowizja STRATTON (ułamek 0..1) oraz Podwyżka STRATTON (ułamek 0..1)
            // Zgodnie z frontendem calc: PAYROLL_CONFIG.OFFERS -> { percent, raisePercent }
            $table->decimal('commission_percent', 5, 4)->nullable()->after('currency');
            $table->decimal('stratton_raise_percent', 5, 4)->nullable()->after('commission_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['commission_percent', 'stratton_raise_percent']);
        });
    }
};
