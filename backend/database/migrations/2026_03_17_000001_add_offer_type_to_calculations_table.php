<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            // QUICK_SIMULATION = szybka oferta szacunkowa (bez listy płac)
            // DETAILED         = pełna kalkulacja z listy płac
            if (!Schema::hasColumn('calculations', 'offer_type')) {
                $table->enum('offer_type', ['QUICK_SIMULATION', 'DETAILED'])
                      ->nullable()
                      ->after('status');
            }
            // Allow saving quick simulations without a meeting (only client_id required)
            if (Schema::hasColumn('calculations', 'meeting_id')) {
                $table->foreignId('meeting_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            if (Schema::hasColumn('calculations', 'offer_type')) {
                $table->dropColumn('offer_type');
            }
        });
    }
};
