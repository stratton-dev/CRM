<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('leadowiec_opiekun_id')->nullable()->after('role_id');
            $table->foreign('leadowiec_opiekun_id')->references('id')->on('users')->nullOnDelete();
            $table->decimal('leadowiec_commission_rate', 5, 4)->default(0.0300)->after('leadowiec_opiekun_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('added_by_user_id')->nullable()->after('organization_id');
            $table->foreign('added_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('added_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['added_by_user_id']);
            $table->dropIndex(['added_by_user_id']);
            $table->dropColumn('added_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['leadowiec_opiekun_id']);
            $table->dropColumn(['leadowiec_opiekun_id', 'leadowiec_commission_rate']);
        });
    }
};
