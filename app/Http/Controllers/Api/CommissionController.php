<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmCommissionDistribution;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Commission\CommissionCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function __construct(private readonly CommissionCalculatorService $calculator)
    {
    }

    /**
     * POST /v1/commission/preview
     *
     * Body: { source_user_supabase_id, base_amount }
     * Bez zapisu — tylko wylicz i zwróć rozkład.
     */
    public function preview(Request $request, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $data = $request->validate([
            'source_user_supabase_id' => 'required|string|max:255',
            'base_amount' => 'required|numeric|min:0',
        ]);

        $items = $this->calculator->calculate(
            $data['source_user_supabase_id'],
            (float) $data['base_amount']
        );

        if (empty($items)) {
            return response()->json(['message' => 'Source user not found.'], 404);
        }

        return response()->json([
            'sourceUserSupabaseId' => $data['source_user_supabase_id'],
            'baseAmount' => (float) $data['base_amount'],
            'items' => $this->formatItems($items),
            'totals' => [
                'paidOut' => array_sum(array_column($items, 'amount')),
                'levels' => count($items),
            ],
        ]);
    }

    /**
     * POST /v1/commission/distributions
     *
     * Body: { source_user_supabase_id, base_amount, period?, source?, source_reference?, note? }
     * Zapisuje + zwraca pełny distribution z items.
     */
    public function store(Request $request, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $data = $request->validate([
            'source_user_supabase_id' => 'required|string|max:255',
            'base_amount' => 'required|numeric|min:0',
            'period' => 'nullable|string|max:16',
            'source' => 'nullable|string|in:MANUAL,EBS_WEBHOOK,OFFER_SIGNED',
            'source_reference' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2048',
        ]);

        $actorId = User::query()
            ->where('supabase_id', $context->actorSupabaseId())
            ->value('id');

        $distribution = $this->calculator->persistDistribution(
            $data['source_user_supabase_id'],
            (float) $data['base_amount'],
            $data['source'] ?? 'MANUAL',
            $data['period'] ?? null,
            $data['source_reference'] ?? null,
            $data['note'] ?? null,
            $actorId ? (int) $actorId : null
        );

        return response()->json($this->formatDistribution($distribution), 201);
    }

    /**
     * GET /v1/commission/distributions
     */
    public function index(Request $request, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $query = CrmCommissionDistribution::query()->with('items')->orderByDesc('created_at');

        if ($period = $request->string('period')->toString()) {
            $query->where('period', $period);
        }
        if ($source = $request->string('source')->toString()) {
            $query->where('source', $source);
        }
        if ($userId = $request->string('source_user_supabase_id')->toString()) {
            $query->where('source_user_supabase_id', $userId);
        }

        $limit = max(1, min(200, (int) $request->input('limit', 50)));
        $distributions = $query->limit($limit)->get();

        return response()->json(
            $distributions->map(fn ($d) => $this->formatDistribution($d))
        );
    }

    /**
     * DELETE /v1/commission/distributions/{id}
     */
    public function destroy(CrmCommissionDistribution $distribution, TokenContext $context): JsonResponse
    {
        $this->authorizeAdmin($context);

        $distribution->delete();
        return response()->json(['deleted' => true]);
    }

    /**
     * GET /v1/commission/my-settlements?period=YYYY-MM
     *
     * Zwraca wszystkie items distribution-ów gdzie current user (lub ktoś
     * z jego subtree dla MANAGER/DIRECTOR/ADMIN) jest receiverem.
     */
    public function mySettlements(Request $request, TokenContext $context): JsonResponse
    {
        $actorUuid = $context->actorSupabaseId();
        if ($actorUuid === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $actor = User::query()->where('supabase_id', $actorUuid)->first();
        if (!$actor) {
            return response()->json(['message' => 'User not found in CRM.'], 404);
        }

        $role = $context->primaryRole();
        $receiverScope = $this->buildReceiverScope($actor, $role);

        $query = \App\Models\CrmCommissionDistributionItem::query()
            ->whereIn('receiver_user_supabase_id', $receiverScope)
            ->with('distribution');

        if ($period = $request->string('period')->toString()) {
            $query->whereHas('distribution', fn ($q) => $q->where('period', $period));
        }

        $items = $query->orderByDesc('created_at')->limit(500)->get();

        // Preload source + receiver users (bulk)
        $userIds = $items
            ->flatMap(fn ($it) => [$it->receiver_user_supabase_id, $it->distribution?->source_user_supabase_id])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $users = User::query()->whereIn('supabase_id', $userIds)->get()->keyBy('supabase_id');

        $rows = $items->map(function ($it) use ($users) {
            $d = $it->distribution;
            $source = $d ? ($users[$d->source_user_supabase_id] ?? null) : null;
            $receiver = $users[$it->receiver_user_supabase_id] ?? null;
            $sourceRole = $source?->role_cached;
            $receiverRole = $receiver?->role_cached;
            $context = $this->classifyContext($sourceRole, $receiverRole, (int) $it->level);

            return [
                'id' => $it->id,
                'distributionId' => $it->distribution_id,
                'period' => $d?->period,
                'sourceKind' => $d?->source,
                'sourceReference' => $d?->source_reference,
                'baseAmount' => (float) ($d?->base_amount ?? 0),
                'rate' => (float) $it->rate,
                'amount' => (float) $it->amount,
                'level' => (int) $it->level,
                'context' => $context,
                'source' => $source ? [
                    'userSupabaseId' => $source->supabase_id,
                    'name' => $source->name,
                    'role' => $source->role_cached,
                ] : null,
                'receiver' => $receiver ? [
                    'userSupabaseId' => $receiver->supabase_id,
                    'name' => $receiver->name,
                    'role' => $receiver->role_cached,
                    'isAgentAuthorized' => (bool) $receiver->is_agent_authorized,
                ] : null,
                'createdAt' => optional($it->created_at)->toIso8601String(),
            ];
        })->values();

        // Aggregate summary per receiver
        $byReceiver = [];
        foreach ($rows as $r) {
            $rid = $r['receiver']['userSupabaseId'] ?? 'unknown';
            if (!isset($byReceiver[$rid])) {
                $byReceiver[$rid] = [
                    'receiverUserSupabaseId' => $rid,
                    'name' => $r['receiver']['name'] ?? 'Brak',
                    'role' => $r['receiver']['role'] ?? null,
                    'isAgentAuthorized' => $r['receiver']['isAgentAuthorized'] ?? false,
                    'totalAmount' => 0.0,
                    'selfAmount' => 0.0,
                    'overrideAmount' => 0.0,
                    'agentAmount' => 0.0,
                    'leadowiecChainAmount' => 0.0,
                    'distributionCount' => 0,
                ];
            }
            $byReceiver[$rid]['totalAmount'] += $r['amount'];
            $byReceiver[$rid]['distributionCount']++;
            switch ($r['context']) {
                case 'SELF':              $byReceiver[$rid]['selfAmount'] += $r['amount']; break;
                case 'AGENT':             $byReceiver[$rid]['agentAmount'] += $r['amount']; break;
                case 'LEADOWIEC_CHAIN':   $byReceiver[$rid]['leadowiecChainAmount'] += $r['amount']; break;
                default:                  $byReceiver[$rid]['overrideAmount'] += $r['amount']; break;
            }
        }

        return response()->json([
            'actor' => [
                'userSupabaseId' => $actor->supabase_id,
                'name' => $actor->name,
                'role' => $actor->role_cached,
                'isAgentAuthorized' => (bool) $actor->is_agent_authorized,
            ],
            'scopeUserCount' => count($receiverScope),
            'period' => $request->string('period')->toString() ?: null,
            'totals' => [
                'grossPaid' => $rows->sum('amount'),
                'distributionCount' => $rows->pluck('distributionId')->unique()->count(),
                'itemCount' => $rows->count(),
            ],
            'byReceiver' => array_values($byReceiver),
            'rows' => $rows,
        ]);
    }

    /**
     * Określa kogo widzi current user.
     *
     * @return array<int, string>  Lista supabase_id którzy mogą być receiverami
     */
    private function buildReceiverScope(User $actor, ?string $role): array
    {
        if ($role === 'ADMIN') {
            return User::query()->pluck('supabase_id')->all();
        }
        if (in_array($role, ['DIRECTOR', 'MANAGER'], true)) {
            $scope = [$actor->supabase_id];
            $this->collectSubtree($actor->supabase_id, $scope);
            return $scope;
        }
        return [$actor->supabase_id];
    }

    /**
     * @param array<int, string> $bag
     */
    private function collectSubtree(string $rootUuid, array &$bag, int $depth = 0): void
    {
        if ($depth > 32) return;
        $children = User::query()->where('parent_supabase_id', $rootUuid)->pluck('supabase_id')->all();
        foreach ($children as $childUuid) {
            $bag[] = $childUuid;
            $this->collectSubtree($childUuid, $bag, $depth + 1);
        }
    }

    /**
     * SELF | LEADOWIEC_CHAIN | AGENT | STANDARD_OVERRIDE
     */
    private function classifyContext(?string $sourceRole, ?string $receiverRole, int $level): string
    {
        if ($level === 0) {
            return 'SELF';
        }
        if ($sourceRole === 'LEADOWIEC' && $receiverRole === 'LEADOWIEC') {
            return 'LEADOWIEC_CHAIN';
        }
        if ($sourceRole === 'LEADOWIEC' && $receiverRole !== 'LEADOWIEC') {
            return 'AGENT';
        }
        return 'STANDARD_OVERRIDE';
    }

    private function authorizeAdmin(TokenContext $context): void
    {
        if ($context->primaryRole() !== 'ADMIN') {
            abort(403, 'Tylko admin może operować na prowizjach.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function formatItems(array $items): array
    {
        return array_map(fn ($it) => [
            'level' => $it['level'],
            'userSupabaseId' => $it['user_supabase_id'],
            'name' => $it['name'],
            'role' => $it['role'],
            'rate' => (float) $it['rate'],
            'amount' => (float) $it['amount'],
            'skipped' => (bool) $it['skipped'],
            'skipReason' => $it['skip_reason'],
        ], $items);
    }

    private function formatDistribution(CrmCommissionDistribution $d): array
    {
        return [
            'id' => $d->id,
            'sourceUserSupabaseId' => $d->source_user_supabase_id,
            'baseAmount' => (float) $d->base_amount,
            'period' => $d->period,
            'source' => $d->source,
            'sourceReference' => $d->source_reference,
            'note' => $d->note,
            'createdByUserId' => $d->created_by_user_id,
            'createdAt' => optional($d->created_at)->toIso8601String(),
            'items' => $d->items->map(fn ($it) => [
                'id' => $it->id,
                'level' => (int) $it->level,
                'receiverUserSupabaseId' => $it->receiver_user_supabase_id,
                'receiverRoleAtTime' => $it->receiver_role_at_time,
                'rate' => (float) $it->rate,
                'amount' => (float) $it->amount,
            ])->all(),
        ];
    }
}
