<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeesController extends Controller
{
    public function index(Request $request)
    {
        $q = Employee::query()->with('company:id,name');

        if ($companyId = $request->integer('company_id')) {
            $q->where('company_id', $companyId);
        }
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Employee $employee)
    {
        return $employee->load('company:id,name');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'birth_date' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
            'gender' => 'nullable|in:M,K,O',
            'contract_type' => 'nullable|in:ETAT,ZLECENIE,B2B,DZIELO',
            'zus_type' => 'nullable|string|max:20',
            'kup' => 'nullable|integer|min:0',
            'kup_percent' => 'nullable|numeric|min:0|max:1',
            'tax_free_amount' => 'nullable|integer|min:0',
            'kzp' => 'nullable|boolean',
            'net_total' => 'nullable|numeric',
            'net_cash' => 'nullable|numeric',
            'active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        $employee = Employee::create($data);
        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'birth_date' => 'nullable|date',
            'age' => 'nullable|integer|min:0|max:120',
            'gender' => 'nullable|in:M,K,O',
            'contract_type' => 'nullable|in:ETAT,ZLECENIE,B2B,DZIELO',
            'zus_type' => 'nullable|string|max:20',
            'kup' => 'nullable|integer|min:0',
            'kup_percent' => 'nullable|numeric|min:0|max:1',
            'tax_free_amount' => 'nullable|integer|min:0',
            'kzp' => 'nullable|boolean',
            'net_total' => 'nullable|numeric',
            'net_cash' => 'nullable|numeric',
            'active' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);
        $employee->update($data);
        return $employee;
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->noContent();
    }
}
