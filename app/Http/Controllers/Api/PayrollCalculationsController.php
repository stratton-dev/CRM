<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollCalculation;
use Illuminate\Http\Request;

class PayrollCalculationsController extends Controller
{
    public function index(Request $request)
    {
        $q = PayrollCalculation::query()
            ->with(['company:id,name', 'employee:id,first_name,last_name', 'offer:id,number']);

        if ($companyId = $request->integer('company_id')) {
            $q->where('company_id', $companyId);
        }
        if ($employeeId = $request->integer('employee_id')) {
            $q->where('employee_id', $employeeId);
        }
        if ($offerId = $request->integer('offer_id')) {
            $q->where('offer_id', $offerId);
        }
        if ($year = $request->integer('period_year')) {
            $q->where('period_year', $year);
        }
        if ($month = $request->integer('period_month')) {
            $q->where('period_month', $month);
        }
        if ($source = $request->string('source')->toString()) {
            $q->where('source', $source);
        }

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(PayrollCalculation $payrollCalculation)
    {
        return $payrollCalculation->load(['company:id,name', 'employee:id,first_name,last_name', 'offer:id,number']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'employee_id' => 'nullable|exists:employees,id',
            'offer_id' => 'nullable|exists:offers,id',
            'created_by' => 'nullable|exists:users,id',
            'period_year' => 'nullable|integer|min:1900|max:3000',
            'period_month' => 'nullable|integer|min:1|max:12',
            'engine_version' => 'nullable|string|max:32',
            'config_version' => 'nullable|string|max:64',
            'source' => 'nullable|in:manual,import,api,ui',
            'inputs_json' => 'required|array',
            'outputs_json' => 'nullable|array',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $calc = PayrollCalculation::create($data);
        return response()->json($calc, 201);
    }

    public function update(Request $request, PayrollCalculation $payrollCalculation)
    {
        $data = $request->validate([
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'employee_id' => 'sometimes|nullable|exists:employees,id',
            'offer_id' => 'sometimes|nullable|exists:offers,id',
            'created_by' => 'sometimes|nullable|exists:users,id',
            'period_year' => 'nullable|integer|min:1900|max:3000',
            'period_month' => 'nullable|integer|min:1|max:12',
            'engine_version' => 'nullable|string|max:32',
            'config_version' => 'nullable|string|max:64',
            'source' => 'nullable|in:manual,import,api,ui',
            'inputs_json' => 'sometimes|array',
            'outputs_json' => 'nullable|array',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);
        $payrollCalculation->update($data);
        return $payrollCalculation->refresh();
    }

    public function destroy(PayrollCalculation $payrollCalculation)
    {
        $payrollCalculation->delete();
        return response()->noContent();
    }
}
