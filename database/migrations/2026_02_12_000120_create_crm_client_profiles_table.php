<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['NEW', 'IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT', 'SPECIAL_OFFER', 'RESIGNED', 'SIGNED', 'TERMINATED'])->default('NEW');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->integer('employees_total')->default(0);
            $table->integer('employees_uop')->default(0);
            $table->integer('employees_uz')->default(0);
            $table->decimal('avg_wage_uop', 12, 2)->default(0);
            $table->decimal('avg_wage_uz', 12, 2)->default(0);
            $table->decimal('service_fee_percent', 6, 2)->default(0);
            $table->date('offer_sent_date')->nullable();
            $table->date('contract_signed_date')->nullable();
            $table->date('reservation_end_date')->nullable();
            $table->json('analysis_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_profiles');
    }
};
