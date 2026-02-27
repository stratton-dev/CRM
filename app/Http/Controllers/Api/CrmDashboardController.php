<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardNews;
use App\Models\CrmClientProfile;
use App\Models\Calculation;
use App\Models\CrmInvoice;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $userId = $this->resolveUserId($request->input('user_id'));
        $viewScope = $request->input('view_scope', 'mine');
        $currentUser = $request->user();

        $now = now();
        $fromDate = $request->date('from_date')?->startOfDay() ?? $now->copy()->startOfMonth();
        $toDate = $request->date('to_date')?->endOfDay() ?? $now->copy()->endOfMonth();

        // Scope filter helper
        $applyScope = function ($query, $userField = 'user_id') use ($userId, $viewScope, $currentUser) {
            if ($viewScope === 'all' && ($currentUser?->role_cached === 'ADMIN' || $currentUser?->role?->code === 'ADMIN')) {
                return $query;
            }

            if (str_starts_with($viewScope ?? '', 'role:')) {
                $roleCode = substr($viewScope, 5);
                $relation = ($userField === 'owner_user_id') ? 'owner' : 'user';
                return $query->whereHas($relation, fn($q) => $q->where('role_cached', $roleCode)->orWhereHas('role', fn($r) => $r->where('code', $roleCode)));
            }

            if ($viewScope === 'mine' || !$viewScope) {
                return $userId ? $query->where($userField, $userId) : $query;
            }

            return $userId ? $query->where($userField, $userId) : $query;
        };

        $meetingsQuery = Meeting::query()
            ->with('client:id,name,nip')
            ->where('status', 'open');

        $meetingsQuery = $applyScope($meetingsQuery, 'user_id');

        $events = $meetingsQuery
            ->orderByRaw('COALESCE(resume_at, updated_at, created_at) asc')
            ->limit(12)
            ->get()
            ->map(static function (Meeting $meeting) {
                $startAt = $meeting->resume_at ?: ($meeting->updated_at ?: $meeting->created_at);
                return [
                    'id' => $meeting->id,
                    'title' => 'Spotkanie: ' . ($meeting->client?->name ?? 'Klient'),
                    'start_at' => optional($startAt)->toISOString(),
                ];
            });

        $news = CrmDashboardNews::query()
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        $calculationsQuery = Calculation::query()
            ->with('meeting.client:id,name,nip');

        if ($viewScope === 'all' && ($currentUser?->role_cached === 'ADMIN' || $currentUser?->role?->code === 'ADMIN')) {
            // No filter
        } else if (str_starts_with($viewScope ?? '', 'role:')) {
            $roleCode = substr($viewScope, 5);
            $calculationsQuery->whereHas('meeting.user', fn($q) => $q->where('role_cached', $roleCode)->orWhereHas('role', fn($r) => $r->where('code', $roleCode)));
        } else {
            $calculationsQuery->when($userId, fn ($q) => $q->whereHas('meeting', fn ($m) => $m->where('user_id', $userId)));
        }

        $periodCalculations = (clone $calculationsQuery)->whereBetween('created_at', [$fromDate, $toDate]);
        $totalSavings = (int) $periodCalculations->sum('savings_amount');
        $readyCount = (clone $periodCalculations)->when(
            Schema::hasColumn('calculations', 'status'),
            fn ($q) => $q->whereIn('status', ['READY', 'SENT']),
            fn ($q) => $q
        )->count();

        $newLeadsQuery = CrmClientProfile::query()
            ->where('status', 'NEW');
        $newLeadsQuery = $applyScope($newLeadsQuery, 'owner_user_id');
        $newLeads = $newLeadsQuery->count();

        $targetSavings = 100000;
        $targetReady = 20;
        $targetLeads = 30;

        $kpis = [
            [
                'id' => 1,
                'title' => 'Jednostki rozliczeniowe',
                'value' => number_format($totalSavings, 0, '.', ' '),
                'score' => $this->scoreForTarget($totalSavings, $targetSavings),
                'min_target' => number_format($targetSavings, 0, '.', ' '),
                'subtitle' => 'Kalkulacje z bieżącego miesiąca',
                'missing' => number_format(max(0, $targetSavings - $totalSavings), 0, '.', ' '),
            ],
            [
                'id' => 2,
                'title' => 'Kalkulacje wysłane',
                'value' => (string) $readyCount,
                'score' => $this->scoreForTarget($readyCount, $targetReady),
                'min_target' => (string) $targetReady,
                'subtitle' => 'Status READY / SENT',
                'missing' => (string) max(0, $targetReady - $readyCount),
            ],
            [
                'id' => 3,
                'title' => 'Nowe spotkania',
                'value' => (string) $newLeads,
                'score' => $this->scoreForTarget($newLeads, $targetLeads),
                'min_target' => (string) $targetLeads,
                'subtitle' => 'Status NEW',
                'missing' => (string) max(0, $targetLeads - $newLeads),
            ],
            [
                'id' => 4,
                'title' => 'Wskaźnik utrzymania umów',
                'value' => '75%',
                'score' => 75,
                'min_target' => '80%',
                'subtitle' => 'Konwersja',
                'missing' => '5%',
            ],
        ];

        $calculations = (clone $calculationsQuery)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(static function (Calculation $calc) {
                return [
                    'id' => $calc->id,
                    'company' => $calc->meeting?->client?->name ?? 'Klient',
                    'nip' => $calc->meeting?->client?->nip ?? null,
                    'meeting_id' => $calc->meeting_id,
                    'client_id' => $calc->meeting?->client_id ?? null,
                    'calculation_date' => optional($calc->created_at)->toISOString(),
                    'valid_until' => optional($calc->valid_until)->toDateString(),
                    'status' => $calc->status ?? null,
                ];
            });

        $overdueQuery = CrmInvoice::query()
            ->with('client:id,name')
            ->where('status', 'UNPAID')
            ->whereDate('issue_date', '<=', $now->copy()->subDays(14));

        $issueFrom = $fromDate->copy()->subDays(14)->toDateString();
        $issueTo = $toDate->copy()->subDays(14)->toDateString();
        $overdueQuery->whereBetween('issue_date', [$issueFrom, $issueTo]);

        if ($userId) {
            $overdueQuery->whereExists(function ($q) use ($userId) {
                $q->selectRaw('1')
                    ->from('crm_client_profiles')
                    ->whereColumn('crm_client_profiles.client_id', 'crm_invoices.client_id')
                    ->where('owner_user_id', $userId);
            });
        }

        $overdue = $overdueQuery
            ->orderBy('issue_date')
            ->limit(50)
            ->get()
            ->map(static function (CrmInvoice $invoice) use ($now) {
                $issueDate = $invoice->issue_date ? $invoice->issue_date->copy() : null;
                $dueDate = $issueDate ? $issueDate->copy()->addDays(14) : null;
                $daysOverdue = $dueDate ? $dueDate->diffInDays($now, false) : null;
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'company' => $invoice->client?->name ?? 'Klient',
                    'amount_gross' => (float) $invoice->amount_gross,
                    'issue_date' => $issueDate?->toDateString(),
                    'due_date' => $dueDate?->toDateString(),
                    'days_overdue' => $daysOverdue !== null ? max(0, (int) $daysOverdue) : null,
                    'status' => $invoice->status,
                ];
            });

        return response()->json([
            'events' => $events,
            'news' => $news,
            'kpis' => $kpis,
            'calculations' => $calculations,
            'overdue_invoices' => $overdue,
        ]);
    }

    private function formatCurrency(int $value): string
    {
        return number_format($value, 0, '.', ' ') . ' PLN';
    }

    private function scoreForTarget(int $value, int $target): int
    {
        if ($target <= 0) return 0;
        return (int) min(100, round(($value / $target) * 100));
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) return null;
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('keycloak_id', (string) $value);
        } else {
            $query->where('keycloak_id', (string) $value);
        }
        return $query->first()?->id;
    }
}
