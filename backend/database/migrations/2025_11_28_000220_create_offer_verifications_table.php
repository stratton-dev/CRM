<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['email', 'sms']);
            $table->string('code');
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_verifications');
    }
};
