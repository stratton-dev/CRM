<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardKpi;
use App\Models\User;
use Illuminate\Http\Request;

class CrmDashboardKpisController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmDashboardKpi::query();
        if ($userId = $this->resolveUserId($request->input('user_id'))) {
            $q->where('user_id', $userId);
        }
        return $q->orderBy('id')->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'title' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'score' => 'nullable|integer|min:0|max:100',
            'min_target' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'missing' => 'nullable|string|max:255',
        ]);
        $kpi = CrmDashboardKpi::create([
            'user_id' => $this->resolveUserId($data['user_id'] ?? null),
            'title' => $data['title'],
            'value' => $data['value'],
            'score' => $data['score'] ?? 0,
            'min_target' => $data['min_target'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'missing' => $data['missing'] ?? null,
        ]);
        return response()->json($kpi, 201);
    }

    public function update(Request $request, CrmDashboardKpi $crmDashboardKpi)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'title' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required|string|max:255',
            'score' => 'nullable|integer|min:0|max:100',
            'min_target' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'missing' => 'nullable|string|max:255',
        ]);
        if (array_key_exists('user_id', $data)) {
            $crmDashboardKpi->user_id = $this->resolveUserId($data['user_id'] ?? null);
        }
        $crmDashboardKpi->fill($data);
        $crmDashboardKpi->save();
        return $crmDashboardKpi;
    }

    public function destroy(CrmDashboardKpi $crmDashboardKpi)
    {
        $crmDashboardKpi->delete();
        return response()->noContent();
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) return null;
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('supabase_id', (string) $value);
        } else {
            $query->where('supabase_id', (string) $value);
        }
        return $query->first()?->id;
    }
}
