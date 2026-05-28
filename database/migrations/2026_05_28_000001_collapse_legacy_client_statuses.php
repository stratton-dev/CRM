<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_STATUSES = ['OFFER_PREPARING', 'OFFER_GENERATED', 'CALCULATION_SENT', 'SPECIAL_OFFER'];
    private const ALLOWED_STATUSES = ['NEW', 'IN_TALKS', 'RESIGNED', 'SIGNED', 'TERMINATED'];
    private const CONSTRAINT_NAME = 'crm_client_profiles_status_check';

    public function up(): void
    {
        if (!Schema::hasTable('crm_client_profiles')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        DB::table('crm_client_profiles')
            ->whereIn('status', self::LEGACY_STATUSES)
            ->update(['status' => 'NEW']);

        if (Schema::hasTable('crm_statuses')) {
            DB::table('crm_statuses')->whereIn('key', self::LEGACY_STATUSES)->delete();
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE crm_client_profiles DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
            $allowed = implode(',', array_map(fn ($s) => "'" . $s . "'", self::ALLOWED_STATUSES));
            DB::statement("ALTER TABLE crm_client_profiles ADD CONSTRAINT " . self::CONSTRAINT_NAME . " CHECK (status IN ($allowed))");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('crm_client_profiles')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE crm_client_profiles DROP CONSTRAINT IF EXISTS ' . self::CONSTRAINT_NAME);
            $full = array_merge(self::ALLOWED_STATUSES, self::LEGACY_STATUSES);
            $allowed = implode(',', array_map(fn ($s) => "'" . $s . "'", $full));
            DB::statement("ALTER TABLE crm_client_profiles ADD CONSTRAINT " . self::CONSTRAINT_NAME . " CHECK (status IN ($allowed))");
        }
    }
};
