<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content'); // WYSIWYG HTML content
            $table->string('category'); // EVENTS, UPDATES, SALES, ALERT
            $table->json('target_roles')->nullable(); // Array of roles e.g. ["SALES", "MANAGER"] or null for all
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('organization_id')->nullable()->constrained('organizations'); // Optional tenant scoping

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
