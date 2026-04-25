<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardCalculation;
use App\Models\User;
use Illuminate\Http\Request;

class CrmDashboardCalculationsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmDashboardCalculation::query();
        if ($userId = $this->resolveUserId($request->input('user_id'))) {
            $q->where('user_id', $userId);
        }
        return $q->orderByDesc('calculation_date')->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'company' => 'required|string|max:255',
            'nip' => 'nullable|string|max:32',
            'meeting_id' => 'nullable|string|max:64',
            'calculation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);
        $calc = CrmDashboardCalculation::create([
            'user_id' => $this->resolveUserId($data['user_id'] ?? null),
            'company' => $data['company'],
            'nip' => $data['nip'] ?? null,
            'meeting_id' => $data['meeting_id'] ?? null,
            'calculation_date' => $data['calculation_date'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'status' => $data['status'] ?? null,
        ]);
        return response()->json($calc, 201);
    }

    public function update(Request $request, CrmDashboardCalculation $crmDashboardCalculation)
    {
        $data = $request->validate([
            'user_id' => 'nullable',
            'company' => 'sometimes|required|string|max:255',
            'nip' => 'nullable|string|max:32',
            'meeting_id' => 'nullable|string|max:64',
            'calculation_date' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'status' => 'nullable|string|max:50',
        ]);
        if (array_key_exists('user_id', $data)) {
            $crmDashboardCalculation->user_id = $this->resolveUserId($data['user_id'] ?? null);
        }
        $crmDashboardCalculation->fill($data);
        $crmDashboardCalculation->save();
        return $crmDashboardCalculation;
    }

    public function destroy(CrmDashboardCalculation $crmDashboardCalculation)
    {
        $crmDashboardCalculation->delete();
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
