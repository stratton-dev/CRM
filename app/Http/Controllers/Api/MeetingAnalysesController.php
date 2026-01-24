<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeetingAnalysis;
use Illuminate\Http\Request;

class MeetingAnalysesController extends Controller
{
    public function index(Request $request)
    {
        $q = MeetingAnalysis::query();
        if ($meetingId = $request->integer('meeting_id')) {
            $q->where('meeting_id', $meetingId);
        }
        return $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
    }

    public function show(MeetingAnalysis $meetingAnalysis)
    {
        return $meetingAnalysis->load('meeting:id,client_id,user_id');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meeting_id' => 'required|exists:meetings,id',
            'industry' => 'nullable|string|max:255',
            'tax_model' => 'nullable|string|max:255',
            'zus_cost_level' => 'nullable|integer',
            'investments_planned' => 'nullable|boolean',
            'expected_savings' => 'nullable|integer',
            'debt_level' => 'nullable|string|max:255',
        ]);
        $analysis = MeetingAnalysis::create($data);
        return response()->json($analysis, 201);
    }

    public function update(Request $request, MeetingAnalysis $meetingAnalysis)
    {
        $data = $request->validate([
            'industry' => 'nullable|string|max:255',
            'tax_model' => 'nullable|string|max:255',
            'zus_cost_level' => 'nullable|integer',
            'investments_planned' => 'nullable|boolean',
            'expected_savings' => 'nullable|integer',
            'debt_level' => 'nullable|string|max:255',
        ]);
        $meetingAnalysis->update($data);
        return $meetingAnalysis;
    }

    public function destroy(MeetingAnalysis $meetingAnalysis)
    {
        $meetingAnalysis->delete();
        return response()->noContent();
    }
}
