<?php

namespace App\Services\Commission;

use App\Models\CrmCommissionChainOverride;
use App\Models\CrmCommissionDistribution;
use App\Models\CrmCommissionDistributionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Override commission engine.
 *
 * Dwa różne tryby zależnie od roli SOURCE (kto przyniósł deal):
 *
 *  TRYB A — LEADOWIEC source:
 *    • Self (level 0): default 10% (config commission.leadowiec.self_rate)
 *      lub users.override_commission_rate jeśli ustawiony jawnie.
 *    • Walk UP chain — TYLKO LEADOWIEC ancestor-ów, max 2 poziomy:
 *        L1 LEADOWIEC parent: default 5% (commission.leadowiec.l1_rate)
 *        L2 LEADOWIEC grandparent: default 2% (commission.leadowiec.l2_rate)
 *        L3+ LEADOWIEC: ignorujemy (cap chain)
 *    • Znajdź AGENTA: pierwszy non-LEADOWIEC w chain z
 *      is_agent_authorized=true. Dostaje default 10%
 *      (commission.agent.default_rate). NIE walk-ujemy chain agenta.
 *    • Per-relacja override (crm_commission_chain_overrides) nadal
 *      wygrywa nad defaultami gdziekolwiek się pojawi.
 *
 *  TRYB B — wszystko inne (SALES/MANAGER/DIRECTOR/ADMIN source):
 *    • Self z users.override_commission_rate, brak default = 0
 *    • Ancestor z per-relacja override, brak = 0
 *    • Pełny walk UP do roota (max depth 32)
 *
 *  Wspólne: pomijani blocked + is_removed_from_structure.
 */
class CommissionCalculatorService
{
    private const MAX_DEPTH = 32;

    /**
     * @return array<int, array{
     *   level:int, user_supabase_id:string, name:string, role:string,
     *   rate:float, amount:float, skipped:bool, skip_reason:?string
     * }>
     */
    public function calculate(string $sourceUserSupabaseId, float $baseAmount): array
    {
        $source = User::query()->where('supabase_id', $sourceUserSupabaseId)->first();
        if (!$source) {
            return [];
        }

        if (($source->role_cached ?? '') === 'LEADOWIEC') {
            return $this->calculateForLeadowiec($source, $baseAmount);
        }

        return $this->calculateForChain($source, $baseAmount);
    }

    /**
     * Standard chain — pełny walk UP po per-relacja override-ach.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateForChain(User $source, float $baseAmount): array
    {
        $overrides = CrmCommissionChainOverride::query()
            ->where('sub_member_supabase_id', $source->supabase_id)
            ->pluck('rate', 'ancestor_supabase_id')
            ->toArray();

        $items = [];
        $selfRate = (float) ($source->override_commission_rate ?? 0);
        $items[] = $this->buildItem($source, 0, $baseAmount, $selfRate);

        $cursor = $source;
        $level = 1;
        $visited = [$source->supabase_id => true];

        while ($level < self::MAX_DEPTH && $cursor->parent_supabase_id) {
            $parentUuid = $cursor->parent_supabase_id;
            if (isset($visited[$parentUuid])) {
                break;
            }
            $parent = User::query()->where('supabase_id', $parentUuid)->first();
            if (!$parent) {
                break;
            }
            $rate = isset($overrides[$parentUuid]) ? (float) $overrides[$parentUuid] : 0.0;
            $items[] = $this->buildItem($parent, $level, $baseAmount, $rate);
            $visited[$parentUuid] = true;
            $cursor = $parent;
            $level++;
        }

        return $items;
    }

    /**
     * MLM leadowca: self + max 2 LEADOWIEC ancestor-ów + agent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calculateForLeadowiec(User $source, float $baseAmount): array
    {
        $overrides = CrmCommissionChainOverride::query()
            ->where('sub_member_supabase_id', $source->supabase_id)
            ->pluck('rate', 'ancestor_supabase_id')
            ->toArray();

        $defaults = config('commission.leadowiec');
        $agentDefault = (float) (config('commission.agent.default_rate') ?? 0.10);
        $maxLeadowiecDepth = (int) ($defaults['max_chain_depth'] ?? 2);

        $items = [];

        // Level 0 — self leadowiec
        $selfRate = $source->override_commission_rate !== null
            ? (float) $source->override_commission_rate
            : (float) ($defaults['self_rate'] ?? 0.10);
        $items[] = $this->buildItem($source, 0, $baseAmount, $selfRate);

        // LEADOWIEC chain — TYLKO leadowcy, max N poziomów
        $cursor = $source;
        $leadowiecLevel = 1;
        $visited = [$source->supabase_id => true];

        while ($cursor->parent_supabase_id) {
            $parentUuid = $cursor->parent_supabase_id;
            if (isset($visited[$parentUuid])) {
                break;
            }
            $parent = User::query()->where('supabase_id', $parentUuid)->first();
            if (!$parent) {
                break;
            }
            $visited[$parentUuid] = true;
            $cursor = $parent;

            $parentRole = $parent->role_cached ?? '';

            if ($parentRole === 'LEADOWIEC') {
                if ($leadowiecLevel > $maxLeadowiecDepth) {
                    // Wyczerpaliśmy cap — przerywamy bez szukania dalej agenta,
                    // bo agent jest pierwszym NIE-leadowcem powyżej. Ale my już
                    // tylko skaczemy po leadowcach więc warto sprawdzić jeszcze
                    // wyżej dla agenta. Stop tylko dla cap przyznawania %.
                    // Zatem nie break, tylko level wyższy bez dodawania itemu.
                    continue;
                }
                $defaultForLevel = $this->defaultRateForLeadowiecLevel($leadowiecLevel, $defaults);
                $rate = isset($overrides[$parentUuid])
                    ? (float) $overrides[$parentUuid]
                    : $defaultForLevel;
                $items[] = $this->buildItem($parent, $leadowiecLevel, $baseAmount, $rate);
                $leadowiecLevel++;
                continue;
            }

            // Pierwszy NON-LEADOWIEC. Jeśli ma is_agent_authorized → agent.
            if ((bool) $parent->is_agent_authorized) {
                $rate = isset($overrides[$parentUuid])
                    ? (float) $overrides[$parentUuid]
                    : $agentDefault;
                $item = $this->buildItem($parent, $leadowiecLevel, $baseAmount, $rate);
                $item['role_in_distribution'] = 'AGENT';
                $items[] = $item;
            }
            // Niezależnie czy jest agentem czy nie — przestajemy iść wyżej
            // (agent's chain NIE dziedziczy z deala leadowca).
            break;
        }

        return $items;
    }

    private function defaultRateForLeadowiecLevel(int $level, array $defaults): float
    {
        return match ($level) {
            1 => (float) ($defaults['l1_rate'] ?? 0.05),
            2 => (float) ($defaults['l2_rate'] ?? 0.02),
            default => 0.0,
        };
    }

    public function persistDistribution(
        string $sourceUserSupabaseId,
        float $baseAmount,
        string $source,
        ?string $period,
        ?string $sourceReference,
        ?string $note,
        ?int $createdByUserId
    ): CrmCommissionDistribution {
        $items = $this->calculate($sourceUserSupabaseId, $baseAmount);

        return DB::transaction(function () use ($sourceUserSupabaseId, $baseAmount, $period, $source, $sourceReference, $note, $createdByUserId, $items) {
            $distribution = CrmCommissionDistribution::create([
                'source_user_supabase_id' => $sourceUserSupabaseId,
                'base_amount' => $baseAmount,
                'period' => $period,
                'source' => $source,
                'source_reference' => $sourceReference,
                'note' => $note,
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($items as $item) {
                if (!empty($item['skipped'])) {
                    continue;
                }
                CrmCommissionDistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'receiver_user_supabase_id' => $item['user_supabase_id'],
                    'receiver_role_at_time' => $item['role'],
                    'level' => $item['level'],
                    'rate' => $item['rate'],
                    'amount' => $item['amount'],
                ]);
            }

            return $distribution->load('items');
        });
    }

    /**
     * @return array{
     *   level:int, user_supabase_id:string, name:string, role:string,
     *   rate:float, amount:float, skipped:bool, skip_reason:?string
     * }
     */
    private function buildItem(User $user, int $level, float $baseAmount, float $rate): array
    {
        $skipped = false;
        $skipReason = null;

        if ((bool) $user->is_blocked) {
            $skipped = true;
            $skipReason = 'blocked';
        } elseif ((bool) $user->is_removed_from_structure) {
            $skipped = true;
            $skipReason = 'removed_from_structure';
        }

        $amount = $skipped ? 0.0 : round($baseAmount * $rate, 2);

        return [
            'level' => $level,
            'user_supabase_id' => $user->supabase_id,
            'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'role' => $user->role_cached ?? 'UNKNOWN',
            'rate' => $rate,
            'amount' => $amount,
            'skipped' => $skipped,
            'skip_reason' => $skipReason,
        ];
    }
}
