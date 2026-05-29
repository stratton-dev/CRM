<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_offer_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('calculation_id')->nullable();
            $table->string('name');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedInteger('size_bytes')->nullable();
            $table->date('valid_until')->nullable();
            $table->longText('pdf_base64'); // base64-encoded PDF payload
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index('calculation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_offer_pdfs');
    }
};
