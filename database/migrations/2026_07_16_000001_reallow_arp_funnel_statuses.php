<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-dopuszcza 4 wartości statusu potrzebne dla środkowych etapów lejka ARP
 * (Audyt sprzedany / Po wizycie / Raport / Decyzja). Migracja z 2026-05-28
 * „collapse_legacy_client_statuses" zwęziła CHECK constraint do 5 wartości.
 *
 * WAŻNE: to zmiana WYŁĄCZNIE constraintu (schema) — ZERO modyfikowanych wierszy,
 * żadne dane klientów nie są ruszane. W pełni odwracalna (down() wraca do 5 wartości).
 */
return new class extends Migration
{
    private const CONSTRAINT_NAME = 'crm_client_profiles_status_check';
    private const FULL = ['NEW', 'IN_TALKS', 'OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT', 'SPECIAL_OFFER', 'RESIGNED', 'SIGNED', 'TERMINATED'];
    private const COLLAPSED = ['NEW', 'IN_TALKS', 'RESIGNED', 'SIGNED', 'TERMINATED'];

    public function up(): void
    {
        $this->setAllowed(self::FULL);
    }

    public function down(): void
    {
        $this->setAllowed(self::COLLAPSED);
    }

    private function setAllowed(array $statuses): void
    {
        if (!Schema::hasTable('crm_client_profiles')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return; // SQLite (dev/testy) nie egzekwuje tego CHECK-a
        }
        DB::statement('ALTER TABLE crm_client_profiles DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
        $allowed = implode(',', array_map(fn ($s) => "'" . $s . "'", $statuses));
        DB::statement('ALTER TABLE crm_client_profiles ADD CONSTRAINT ' . self::CONSTRAINT_NAME . " CHECK (status IN ($allowed))");
    }
};
