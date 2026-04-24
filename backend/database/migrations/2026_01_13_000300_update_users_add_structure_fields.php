<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('role_id')->constrained('users')->nullOnDelete();
            $table->string('hierarchical_id')->nullable()->after('parent_id');
            $table->string('crm_number')->nullable()->after('hierarchical_id');
            $table->string('rank')->nullable()->after('crm_number');
            $table->string('contract_status')->nullable()->after('rank');
            $table->string('type')->nullable()->after('contract_status');
            $table->json('address_json')->nullable()->after('type');
            $table->json('documents_json')->nullable()->after('address_json');
            $table->boolean('is_removed_from_structure')->default(false)->after('documents_json');
            $table->boolean('is_blocked')->default(false)->after('is_removed_from_structure');
            $table->integer('points')->nullable()->after('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'hierarchical_id',
                'crm_number',
                'rank',
                'contract_status',
                'type',
                'address_json',
                'documents_json',
                'is_removed_from_structure',
                'is_blocked',
                'points',
            ]);
        });
    }
};
