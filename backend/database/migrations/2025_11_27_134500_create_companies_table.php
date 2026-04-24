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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nip', 20)->unique();
            $table->string('regon', 20)->nullable();
            $table->string('krs', 20)->nullable();

            // Address fields
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('postal_code', 12);
            $table->string('city');
            $table->string('country', 2)->default('PL');

            // Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
