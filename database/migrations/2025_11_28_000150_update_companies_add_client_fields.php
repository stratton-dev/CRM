<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            $table->json('address_json')->nullable()->after('krs');
            $table->string('industry')->nullable()->after('address_json');
            $table->string('vat_type')->nullable()->after('industry');
            $table->integer('employee_count')->nullable()->after('vat_type');
            $table->boolean('benefits_enabled')->default(false)->after('employee_count');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['address_json', 'industry', 'vat_type', 'employee_count', 'benefits_enabled']);
        });
    }
};
