<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('autenti_process_id')->nullable()->after('file_path');
            $table->string('autenti_status')->nullable()->after('autenti_process_id');
            $table->string('autenti_file_id')->nullable()->after('autenti_status');
            $table->timestamp('autenti_last_event_at')->nullable()->after('autenti_file_id');
            $table->index('autenti_process_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['autenti_process_id']);
            $table->dropColumn([
                'autenti_process_id',
                'autenti_status',
                'autenti_file_id',
                'autenti_last_event_at',
            ]);
        });
    }
};
