<?php

namespace App\Services\Structure;

use App\Models\User;

/**
 * Wyznacza "agenta" obsługującego klienta zgłoszonego przez LEADOWCA.
 *
 * Agent = najbliższy przełożony w strukturze (walk UP po parent_supabase_id),
 * który NIE jest leadowcem i ma is_agent_authorized=true. To ten sam podmiot,
 * który dostaje prowizję AGENT (patrz CommissionCalculatorService).
 *
 * Fallback (gdy chain-walk nie znajdzie agenta):
 *   1) leadowiec_opiekun_id (jeśli aktywny),
 *   2) pierwszy aktywny ADMIN/DIRECTOR (pula nadzoru).
 *
 * Pomija użytkowników is_blocked oraz is_removed_from_structure.
 */
class AgentResolverService
{
    private const MAX_DEPTH = 32;

    public function resolveForLeadowiec(User $leadowiec): ?User
    {
        $agent = $this->walkUpToAuthorizedAgent($leadowiec);
        if ($agent) {
            return $agent;
        }

        // Fallback 1 — stały opiekun, jeśli istnieje i jest aktywny.
        if ($leadowiec->leadowiec_opiekun_id) {
            $opiekun = User::query()->find($leadowiec->leadowiec_opiekun_id);
            if ($opiekun && $this->isActive($opiekun)) {
                return $opiekun;
            }
        }

        // Fallback 2 — pula nadzoru (admin/dyrektor).
        return User::query()
            ->whereIn('role_cached', ['ADMIN', 'DIRECTOR'])
            ->where('is_blocked', false)
            ->where('is_removed_from_structure', false)
            ->orderByRaw("CASE role_cached WHEN 'DIRECTOR' THEN 0 WHEN 'ADMIN' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();
    }

    /**
     * Idzie w górę łańcucha pomijając leadowców; zwraca pierwszego
     * NON-LEADOWCA z is_agent_authorized=true. Pomija blocked/removed.
     */
    private function walkUpToAuthorizedAgent(User $leadowiec): ?User
    {
        $cursor = $leadowiec;
        $visited = [$leadowiec->supabase_id => true];
        $depth = 0;

        while ($depth < self::MAX_DEPTH && $cursor->parent_supabase_id) {
            $parentUuid = $cursor->parent_supabase_id;
            if (isset($visited[$parentUuid])) {
                break;
            }
            $visited[$parentUuid] = true;

            $parent = User::query()->where('supabase_id', $parentUuid)->first();
            if (!$parent) {
                break;
            }

            $cursor = $parent;
            $depth++;

            // Kontynuuj przez kolejnych leadowców w górę.
            if (($parent->role_cached ?? '') === 'LEADOWIEC') {
                continue;
            }

            // Pierwszy NON-LEADOWIEC: agent tylko jeśli uprawniony i aktywny.
            if ((bool) $parent->is_agent_authorized && $this->isActive($parent)) {
                return $parent;
            }

            // Non-leadowiec bez uprawnienia agenta — nie szukamy wyżej,
            // bo łańcuch leadowca kończy się na pierwszym agencie/handlowcu.
            return null;
        }

        return null;
    }

    private function isActive(User $user): bool
    {
        return !(bool) $user->is_blocked && !(bool) $user->is_removed_from_structure;
    }
}
