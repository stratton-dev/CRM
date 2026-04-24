<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('single');
            $table->string('name');
            $table->string('nip')->unique();
            $table->string('regon')->nullable();
            $table->string('krs')->nullable();
            $table->json('address_json')->nullable();
            $table->timestamp('gus_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
