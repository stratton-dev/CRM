<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->string('token')->nullable()->unique()->after('number');
            $table->timestamp('opened_at')->nullable()->after('token');
            $table->date('expires_at')->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'meeting_id')) {
                $table->dropConstrainedForeignId('meeting_id');
            }
            if (Schema::hasColumn('offers', 'token')) {
                $table->dropUnique('offers_token_unique');
            }
            $columns = array_filter(['token', 'opened_at', 'expires_at'], fn ($col) => Schema::hasColumn('offers', $col));
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
