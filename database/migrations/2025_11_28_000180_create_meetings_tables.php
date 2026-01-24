<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies');
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['open', 'completed', 'expired'])->default('open');
            $table->boolean('calculation_shown')->default(false);
            $table->date('valid_until');
            $table->timestamps();
        });

        Schema::create('meeting_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('industry')->nullable();
            $table->string('tax_model')->nullable();
            $table->integer('zus_cost_level')->nullable();
            $table->boolean('investments_planned')->nullable();
            $table->integer('expected_savings')->nullable();
            $table->string('debt_level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_analysis');
        Schema::dropIfExists('meetings');
    }
};
