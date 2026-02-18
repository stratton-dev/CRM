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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['person', 'sole_proprietorship', 'company'])->default('person');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('nip', 20)->nullable();
            $table->string('regon', 20)->nullable();
            $table->string('krs', 20)->nullable();
            $table->string('pesel', 11)->nullable();
            $table->string('email');
            $table->string('phone');
            $table->json('address_json')->nullable(); // street, house_number, apartment_number, postal_code, city
            $table->string('status')->default('new'); // new, documents_sent, signed, rejected
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->string('type'); // nda, contract, career_path, other
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('autenti_doc_id')->nullable();
            $table->string('status')->default('pending'); // pending, sent, signed, rejected
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidates');
    }
};
