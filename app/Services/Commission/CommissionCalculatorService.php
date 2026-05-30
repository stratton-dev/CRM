<?php

namespace App\Services\Commission;

use App\Models\CrmCommissionDistribution;
use App\Models\CrmCommissionDistributionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Override commission engine.
 *
 * Reguła ustalona z biznesem:
 *  • Każdy ancestor (rekurencyjnie po `parent_supabase_id`) dostaje
 *    procent z TEJ SAMEJ kwoty bazowej — modele niezależne, nie kaskadowe.
 *  • Stawka brana z `users.override_commission_rate` (per użytkownik).
 *  • Self (handlowiec, level 0) też dostaje swoje % z własnego dealu.
 *  • Usunięci ze struktury / zablokowani są pomijani.
 *  • Brak ancestor.rate (NULL) → 0% (zapisujemy pozycję dla audytu).
 */
class CommissionCalculatorService
{
    private const MAX_DEPTH = 32; // safeguard przeciw pętli w danych

    /**
     * @return array<int, array{
     *   level:int, user_supabase_id:string, name:string, role:string,
     *   rate:float, amount:float, skipped:bool, skip_reason:?string
     * }>
     */
    public function calculate(string $sourceUserSupabaseId, float $baseAmount): array
    {
        $items = [];
        $source = User::query()->where('supabase_id', $sourceUserSupabaseId)->first();
        if (!$source) {
            return [];
        }

        $items[] = $this->buildItem($source, 0, $baseAmount);

        $cursor = $source;
        $level = 1;
        $visited = [$source->supabase_id => true];

        while ($level < self::MAX_DEPTH && $cursor->parent_supabase_id) {
            $parentUuid = $cursor->parent_supabase_id;
            if (isset($visited[$parentUuid])) {
                // Wykryto cykl — przerwij.
                break;
            }
            $parent = User::query()->where('supabase_id', $parentUuid)->first();
            if (!$parent) {
                break;
            }
            $items[] = $this->buildItem($parent, $level, $baseAmount);
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
    private function buildItem(User $user, int $level, float $baseAmount): array
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

        $rate = (float) ($user->override_commission_rate ?? 0);
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
