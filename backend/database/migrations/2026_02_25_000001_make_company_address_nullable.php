<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->change();
            $table->string('postal_code', 12)->nullable()->change();
            $table->string('city')->nullable()->change();
            $table->string('country', 2)->default('PL')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Cannot easily revert blindly without knowing if nulls exist,
            // but for structure's sake:
            $table->string('address_line1')->nullable(false)->change();
            $table->string('postal_code', 12)->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }
};
