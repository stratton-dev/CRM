<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill opiekuna leadowca dla istniejących leadowców bez przypisania.
 * Opiekun = najbliższy przełożony NIE-leadowiec (SALES/MANAGER/DIRECTOR) w górę
 * łańcucha parent_supabase_id. Pomija kolejnych leadowców (łańcuch MLM).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mapa supabase_id -> {id, role, parent} dla całej tabeli (mały zbiór userów).
        $bySid = [];
        foreach (DB::table('users')->select('id', 'supabase_id', 'role_cached', 'parent_supabase_id')->get() as $u) {
            if ($u->supabase_id) {
                $bySid[$u->supabase_id] = $u;
            }
        }

        $leadowcy = DB::table('users')
            ->where('role_cached', 'LEADOWIEC')
            ->whereNull('leadowiec_opiekun_id')
            ->select('id', 'parent_supabase_id')
            ->get();

        foreach ($leadowcy as $lead) {
            $opiekunId = $this->resolveOpiekun($lead->parent_supabase_id, $bySid);
            if ($opiekunId !== null) {
                DB::table('users')->where('id', $lead->id)->update(['leadowiec_opiekun_id' => $opiekunId]);
            }
        }
    }

    private function resolveOpiekun(?string $parentSid, array $bySid): ?int
    {
        $visited = [];
        $sid = $parentSid;

        while ($sid && isset($bySid[$sid]) && !isset($visited[$sid])) {
            $visited[$sid] = true;
            $node = $bySid[$sid];
            $role = $node->role_cached ?? '';
            if ($role !== 'LEADOWIEC') {
                return in_array($role, ['SALES', 'MANAGER', 'DIRECTOR'], true) ? (int) $node->id : null;
            }
            $sid = $node->parent_supabase_id;
        }

        return null;
    }

    public function down(): void
    {
        // Brak rollbacku — nie czyścimy przypisań opiekuna (mogły być też ustawione ręcznie).
    }
};
