<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardCalculation;
use App\Models\CrmDashboardEvent;
use App\Models\CrmDashboardKpi;
use App\Models\CrmDashboardNews;
use App\Models\User;
use Illuminate\Http\Request;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $userId = $this->resolveUserId($request->input('user_id'));

        $events = CrmDashboardEvent::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('start_at')
            ->get();

        $news = CrmDashboardNews::query()
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        $kpis = CrmDashboardKpi::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('id')
            ->get();

        $calculations = CrmDashboardCalculation::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('calculation_date')
            ->get();

        return response()->json([
            'events' => $events,
            'news' => $news,
            'kpis' => $kpis,
            'calculations' => $calculations,
        ]);
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
