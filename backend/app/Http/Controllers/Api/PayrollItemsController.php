<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Http\Request;

class PayrollItemsController extends Controller
{
    public function index(Payroll $payroll)
    {
        return $payroll->items()->get();
    }

    public function show(PayrollItem $item)
    {
        return $item->load('payroll:id,month');
    }

    public function store(Request $request, Payroll $payroll)
    {
        $data = $request->validate([
            'employment_type' => 'required|in:UOP,UZ',
            'salary_gross' => 'required|integer',
        ]);
        $data['payroll_id'] = $payroll->id;
        $item = PayrollItem::create($data);
        return response()->json($item, 201);
    }

    public function update(Request $request, PayrollItem $item)
    {
        $data = $request->validate([
            'employment_type' => 'sometimes|in:UOP,UZ',
            'salary_gross' => 'sometimes|integer',
        ]);
        $item->update($data);
        return $item;
    }

    public function destroy(PayrollItem $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
