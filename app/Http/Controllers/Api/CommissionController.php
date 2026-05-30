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
