<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description');
            $table->boolean('required')->default(true);
        });

        Schema::create('client_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('consent_id')->constrained();
            $table->timestamp('accepted_at');
            $table->string('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_consents');
        Schema::dropIfExists('consents');
    }
};
