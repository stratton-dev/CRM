<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;

class PayrollsController extends Controller
{
    public function index(Request $request)
    {
        $q = Payroll::query()->with('client:id,name');

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        if ($month = $request->string('month')->toString()) {
            $q->where('month', $month);
        }
        if ($request->has('imported')) {
            $q->where('imported', $request->boolean('imported'));
        }

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Payroll $payroll)
    {
        return $payroll->load(['client:id,name', 'items']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'month' => 'required|string|max:7',
            'imported' => 'nullable|boolean',
        ]);
        $payroll = Payroll::create($data);
        return response()->json($payroll, 201);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $data = $request->validate([
            'client_id' => 'sometimes|exists:companies,id',
            'month' => 'sometimes|required|string|max:7',
            'imported' => 'nullable|boolean',
        ]);
        $payroll->update($data);
        return $payroll;
    }

    public function destroy(Payroll $payroll)
    {
        $payroll->delete();
        return response()->noContent();
    }
}
