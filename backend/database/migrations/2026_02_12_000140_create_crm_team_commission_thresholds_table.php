<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_team_commission_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('team_group_path')->unique();
            $table->decimal('renewal_commission_rate', 6, 4)->nullable();
            $table->decimal('override_commission_rate', 6, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_team_commission_thresholds');
    }
};
