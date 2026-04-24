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
        Schema::table('calculations', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('calculations', 'value_json')) {
                $blueprint->json('value_json')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculations', function (Blueprint $blueprint) {
            $blueprint->dropColumn('value_json');
        });
    }
};
