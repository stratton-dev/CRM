<?php

namespace App\Services\Commission;

use App\Models\CrmCommissionChainOverride;
use App\Models\CrmCommissionDistribution;
use App\Models\CrmCommissionDistributionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Override commission engine — per-relacja edition.
 *
 * Reguły:
 *  • Self (level 0): stawka z `users.override_commission_rate` na profilu
 *    sub-membera (np. handlowca). To „własna stawka" z własnych dealów.
 *  • Ancestor (level >= 1): lookup w `crm_commission_chain_overrides`
 *    po PARZE (sub_member_supabase_id = źródło dealu, ancestor_supabase_id).
 *    Brak rekordu = 0% (ancestor nic nie dostaje od tego sub-membera).
 *  • Chain idzie rekurencyjnie po `parent_supabase_id` aż do roota.
 *  • Modele niezależne — wszyscy liczeni od tej samej kwoty bazowej.
 *  • Pomijani: blocked + is_removed_from_structure.
 *  • Safeguard max depth = 32.
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

        // Pobierz wszystkie override-y per-relacja dla tego sub-membera (1 query)
        $overrides = CrmCommissionChainOverride::query()
            ->where('sub_member_supabase_id', $sourceUserSupabaseId)
            ->pluck('rate', 'ancestor_supabase_id')
            ->toArray();

        $items = [];

        // Level 0 — self
        $selfRate = (float) ($source->override_commission_rate ?? 0);
        $items[] = $this->buildItem($source, 0, $baseAmount, $selfRate);

        // Level 1..N — chain w górę
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
