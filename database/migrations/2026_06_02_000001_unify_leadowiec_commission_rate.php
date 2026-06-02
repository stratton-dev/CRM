<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ujednolicenie stawki własnej leadowca.
 *
 * users.leadowiec_commission_rate staje się JEDYNYM źródłem prawdy o stawce self
 * leadowca — czyta ją zarówno CommissionCalculatorService (silnik override) jak i
 * LeadowiecController::settlements (Fakturownia). Domyślna stawka: 3% → 10%
 * (spójna z config commission.leadowiec.self_rate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('leadowiec_commission_rate', 5, 4)->default(0.1000)->change();
        });

        // Podbij dotychczasowy default 3% → 10% (nie ruszaj jawnie ustawionych innych stawek).
        DB::table('users')
            ->where('leadowiec_commission_rate', 0.0300)
            ->update(['leadowiec_commission_rate' => 0.1000]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('leadowiec_commission_rate', 5, 4)->default(0.0300)->change();
        });

        DB::table('users')
            ->where('leadowiec_commission_rate', 0.1000)
            ->update(['leadowiec_commission_rate' => 0.0300]);
    }
};
