<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable Row Level Security on every table in the public schema.
 *
 * Why: the Laravel backend connects as the postgres superuser (via pooler),
 * which always bypasses RLS. The Supabase anon key and authenticated key
 * connect as low-privilege Postgres roles and are subject to RLS.
 * With RLS enabled and NO permissive policies defined, those roles get
 * zero direct access to the database — all access must go through the
 * Laravel API.
 *
 * This migration is a no-op on SQLite (local dev).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );

        foreach ($tables as $table) {
            DB::statement(
                sprintf('ALTER TABLE public.%s ENABLE ROW LEVEL SECURITY', $this->quote($table->tablename))
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tables = DB::select(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
        );

        foreach ($tables as $table) {
            DB::statement(
                sprintf('ALTER TABLE public.%s DISABLE ROW LEVEL SECURITY', $this->quote($table->tablename))
            );
        }
    }

    private function quote(string $name): string
    {
        // Safely double-quote the identifier to handle reserved words / special chars.
        return '"' . str_replace('"', '""', $name) . '"';
    }
};
