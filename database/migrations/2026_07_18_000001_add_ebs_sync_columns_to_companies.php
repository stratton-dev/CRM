<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolumny korelacyjne dla integracji CRM → EBS (kierunek A: klient SIGNED
 * tworzony w EBS). Czysto ADDYTYWNE, nullable — zero zmian istniejących danych.
 *   - ebs_company_id : id firmy zwrócone przez EBS (origin=CRM_SYNC), do korelacji
 *     i idempotencji (nie wysyłamy drugi raz).
 *   - ebs_synced_at  : znacznik ostatniej udanej synchronizacji do EBS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'ebs_company_id')) {
                $table->string('ebs_company_id')->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('companies', 'ebs_synced_at')) {
                $table->timestamp('ebs_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }
        Schema::table('companies', function (Blueprint $table) {
            foreach (['ebs_company_id', 'ebs_synced_at'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
