<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('renewal_commission_rate', 6, 4)->nullable()->after('points');
            $table->decimal('override_commission_rate', 6, 4)->nullable()->after('renewal_commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['renewal_commission_rate', 'override_commission_rate']);
        });
    }
};
