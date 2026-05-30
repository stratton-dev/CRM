<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmCommissionChainOverride;
use App\Models\User;
use App\Services\Auth\TokenContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zarządza per-relacją override prowizji dla danego sub-membera.
 *
 *   GET  /v1/users/{user}/commission-chain
 *   PUT  /v1/users/{user}/commission-chain
 */
class CommissionChainController extends Controller
{
    private const MAX_DEPTH = 32;

    public function show(User $user, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $chain = $this->buildChain($user);
        $overrides = CrmCommissionChainOverride::query()
            ->where('sub_member_supabase_id', $user->supabase_id)
            ->pluck('rate', 'ancestor_supabase_id')
            ->toArray();

        $ancestors = array_map(function (User $ancestor) use ($overrides) {
            $uuid = $ancestor->supabase_id;
            return [
                'userSupabaseId' => $uuid,
                'name' => $ancestor->name ?? trim(($ancestor->first_name ?? '') . ' ' . ($ancestor->last_name ?? '')),
                'email' => $ancestor->email,
                'role' => $ancestor->role_cached,
                'isAgentAuthorized' => (bool) $ancestor->is_agent_authorized,
                'rate' => isset($overrides[$uuid]) ? (float) $overrides[$uuid] : null,
            ];
        }, $chain);

        $isLeadowiec = ($user->role_cached ?? '') === 'LEADOWIEC';

        return response()->json([
            'subMember' => [
                'userSupabaseId' => $user->supabase_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role_cached,
                'isAgentAuthorized' => (bool) $user->is_agent_authorized,
                'selfRate' => $user->override_commission_rate !== null ? (float) $user->override_commission_rate : null,
            ],
            'ancestors' => $ancestors,
            'mode' => $isLeadowiec ? 'LEADOWIEC' : 'STANDARD',
            'defaults' => $isLeadowiec ? [
                'selfRate' => (float) config('commission.leadowiec.self_rate'),
                'l1Rate' => (float) config('commission.leadowiec.l1_rate'),
                'l2Rate' => (float) config('commission.leadowiec.l2_rate'),
                'agentRate' => (float) config('commission.agent.default_rate'),
                'maxChainDepth' => (int) config('commission.leadowiec.max_chain_depth'),
            ] : null,
        ]);
    }

    public function update(Request $request, User $user, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $data = $request->validate([
            'self_rate' => 'nullable|numeric|min:0|max:1',
            'overrides' => 'nullable|array',
            'overrides.*.ancestor_supabase_id' => 'required|string|max:255',
            'overrides.*.rate' => 'nullable|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($user, $data) {
            // Self-rate idzie do users.override_commission_rate
            if (array_key_exists('self_rate', $data)) {
                $user->override_commission_rate = $data['self_rate'];
                $user->save();
            }

            if (isset($data['overrides']) && is_array($data['overrides'])) {
                $chainAncestorUuids = collect($this->buildChain($user))
                    ->pluck('supabase_id')
                    ->all();

                foreach ($data['overrides'] as $entry) {
                    $ancestorUuid = $entry['ancestor_supabase_id'];
                    // Sanity: dopuść tylko ancestor-ów którzy są faktycznie w chain.
                    if (!in_array($ancestorUuid, $chainAncestorUuids, true)) {
                        continue;
                    }
                    $rate = $entry['rate'] ?? null;
                    if ($rate === null || $rate === '') {
                        CrmCommissionChainOverride::query()
                            ->where('sub_member_supabase_id', $user->supabase_id)
                            ->where('ancestor_supabase_id', $ancestorUuid)
                            ->delete();
                    } else {
                        CrmCommissionChainOverride::updateOrCreate(
                            [
                                'sub_member_supabase_id' => $user->supabase_id,
                                'ancestor_supabase_id' => $ancestorUuid,
                            ],
                            ['rate' => (float) $rate]
                        );
                    }
                }
            }
        });

        return $this->show($user, $context);
    }

    /**
     * @return User[]
     */
    private function buildChain(User $user): array
    {
        $chain = [];
        $cursor = $user;
        $visited = [$user->supabase_id => true];
        $level = 0;

        while ($level < self::MAX_DEPTH && $cursor->parent_supabase_id) {
            $parentUuid = $cursor->parent_supabase_id;
            if (isset($visited[$parentUuid])) {
                break;
            }
            $parent = User::query()->where('supabase_id', $parentUuid)->first();
            if (!$parent) {
                break;
            }
            $chain[] = $parent;
            $visited[$parentUuid] = true;
            $cursor = $parent;
            $level++;
        }

        return $chain;
    }

    private function authorizeAdmin(TokenContext $context): void
    {
        if ($context->primaryRole() !== 'ADMIN') {
            abort(403, 'Tylko admin może edytować łańcuch prowizji.');
        }
    }
}
