<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
        });

        // Backfill: split existing `name` (assume "First Last [Extra]" format —
        // first token → first_name, the rest joined → last_name).
        $users = DB::table('users')
            ->select(['id', 'name'])
            ->whereNull('first_name')
            ->whereNotNull('name')
            ->get();

        foreach ($users as $user) {
            $trimmed = trim((string) $user->name);
            if ($trimmed === '') {
                continue;
            }
            $parts = preg_split('/\s+/u', $trimmed) ?: [];
            $first = array_shift($parts) ?? '';
            $last = trim(implode(' ', $parts));
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first ?: null,
                'last_name'  => $last !== '' ? $last : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};
