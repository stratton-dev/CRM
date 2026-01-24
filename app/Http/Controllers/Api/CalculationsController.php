<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calculation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CalculationsController extends Controller
{
    public function index(Request $request)
    {
        $q = Calculation::query()->with('meeting:id,client_id,user_id');

        if ($meetingId = $request->integer('meeting_id')) {
            $q->where('meeting_id', $meetingId);
        }

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Calculation $calculation)
    {
        return $calculation->load('meeting:id,client_id,user_id');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meeting_id' => 'required|exists:meetings,id',
            'employee_count' => 'required|integer|min:0',
            'savings_amount' => 'required|integer|min:0',
            'valid_until' => 'required|date',
            'status' => 'nullable|string|max:50',
        ]);
        if (!Schema::hasColumn('calculations', 'status')) {
            unset($data['status']);
        }
        $calculation = Calculation::create($data);
        return response()->json($calculation, 201);
    }

    public function update(Request $request, Calculation $calculation)
    {
        $data = $request->validate([
            'meeting_id' => 'sometimes|exists:meetings,id',
            'employee_count' => 'sometimes|integer|min:0',
            'savings_amount' => 'sometimes|integer|min:0',
            'valid_until' => 'sometimes|date',
            'status' => 'nullable|string|max:50',
        ]);
        if (!Schema::hasColumn('calculations', 'status')) {
            unset($data['status']);
        }
        $calculation->update($data);
        return $calculation;
    }

    public function destroy(Calculation $calculation)
    {
        $calculation->delete();
        return response()->noContent();
    }
}
