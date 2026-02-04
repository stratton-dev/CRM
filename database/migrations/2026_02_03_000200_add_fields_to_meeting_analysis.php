<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_analysis', function (Blueprint $table) {
            $table->string('benefits')->nullable();
            $table->string('project_participation')->nullable();
            $table->string('past_savings')->nullable();
            $table->string('current_savings')->nullable();
            $table->string('planned_investments')->nullable();
            $table->string('declared_savings')->nullable();
            $table->string('debts')->nullable();
            $table->string('vat_model')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_analysis', function (Blueprint $table) {
            $table->dropColumn([
                'benefits',
                'project_participation',
                'past_savings',
                'current_savings',
                'planned_investments',
                'declared_savings',
                'debts',
                'vat_model',
            ]);
        });
    }
};
