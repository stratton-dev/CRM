<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->date('issue_date');
            $table->decimal('amount_net', 12, 2)->default(0);
            $table->decimal('amount_gross', 12, 2)->default(0);
            $table->decimal('service_fee_net', 12, 2)->default(0);
            $table->enum('status', ['PAID', 'UNPAID'])->default('UNPAID');
            $table->string('pdf_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_invoices');
    }
};
