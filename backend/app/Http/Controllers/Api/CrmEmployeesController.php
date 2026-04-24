<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmEmployee;
use Illuminate\Http\Request;

class CrmEmployeesController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmEmployee::query()->with('client:id,name');
        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        return $q->latest()->paginate($request->integer('per_page', 100));
    }

    public function show(CrmEmployee $crmEmployee)
    {
        return $crmEmployee->load('client:id,name');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'pesel' => 'nullable|string|max:32',
            'contract_type' => 'nullable|in:UoP,UZ',
            'benefit_amount' => 'required|numeric|min:0',
        ]);
        $employee = CrmEmployee::create($data);
        return response()->json($employee->load('client:id,name'), 201);
    }

    public function update(Request $request, CrmEmployee $crmEmployee)
    {
        $data = $request->validate([
            'client_id' => 'sometimes|required|exists:companies,id',
            'name' => 'sometimes|required|string|max:255',
            'pesel' => 'nullable|string|max:32',
            'contract_type' => 'nullable|in:UoP,UZ',
            'benefit_amount' => 'sometimes|required|numeric|min:0',
        ]);
        $crmEmployee->update($data);
        return $crmEmployee->load('client:id,name');
    }

    public function destroy(CrmEmployee $crmEmployee)
    {
        $crmEmployee->delete();
        return response()->noContent();
    }
}
