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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nip', 20)->nullable(); // Made nullable for flexibility, but app requires it
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Status tracking
            // new: Freshly added
            // processing: Contact attempt made
            // qualified: Phone call successful + note added (Ready for Stage 2)
            // converted: Moved to Companies/Meetings (Stage 2)
            // rejected: Not interested
            $table->enum('status', ['new', 'processing', 'qualified', 'converted', 'rejected'])->default('new');

            $table->string('source')->default('manual');
            $table->timestamp('last_contact_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
