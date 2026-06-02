<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Services\Structure\StructureService;
use App\Services\Auth\TokenContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CalculationsController extends Controller
{
    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $q = Calculation::query()->with('meeting:id,client_id,user_id');

        $authUser = $request->user();
        $role = $context->primaryRole();

        // LEADOWIEC (read-only): kalkulacje klientów, których sam zgłosił.
        // Meetingi prowadzi agent, więc scope po meeting.client.added_by_user_id.
        if ($authUser && $authUser->role_cached === 'LEADOWIEC') {
            $q->whereHas('meeting.client', function ($c) use ($authUser) {
                $c->where('added_by_user_id', $authUser->id);
            });
        } elseif ($role !== 'ADMIN') {
            $users = $structure->listUsers($context);
            $userIds = collect($users)->pluck('id')->unique()->values()->all();

            if (empty($userIds)) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereHas('meeting', function ($m) use ($userIds) {
                    $m->whereIn('user_id', $userIds);
                });
            }
        }

        if ($meetingId = $request->integer('meeting_id')) {
            $q->where('meeting_id', $meetingId);
        }
        if ($clientId = $request->integer('client_id')) {
            $q->whereHas('meeting', function ($m) use ($clientId) {
                $m->where('client_id', $clientId);
            });
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
            'meeting_id' => 'nullable|exists:meetings,id',
            'client_id'  => 'nullable|exists:companies,id',
            'employee_count' => 'required|integer|min:0',
            'savings_amount' => 'required|integer|min:0',
            'valid_until' => 'required|date',
            'status' => 'nullable|string|max:50',
            'offer_type' => 'nullable|in:QUICK_SIMULATION,DETAILED',
        ]);
        if (!$data['meeting_id'] && !$data['client_id']) {
            return response()->json(['error' => 'Wymagane meeting_id lub client_id.'], 422);
        }
        if (!Schema::hasColumn('calculations', 'status')) {
            unset($data['status']);
        }
        if (!Schema::hasColumn('calculations', 'offer_type')) {
            unset($data['offer_type']);
        }
        $calculation = Calculation::create($data);
        return response()->json($calculation, 201);
    }

    public function update(Request $request, Calculation $calculation)
    {
        $data = $request->validate([
            'meeting_id'     => 'sometimes|nullable|exists:meetings,id',
            'client_id'      => 'sometimes|nullable|exists:companies,id',
            'employee_count' => 'sometimes|integer|min:0',
            'savings_amount' => 'sometimes|integer|min:0',
            'valid_until'    => 'sometimes|date',
            'status'         => 'nullable|string|max:50',
            'offer_type'     => 'nullable|in:QUICK_SIMULATION,DETAILED',
        ]);
        if (!Schema::hasColumn('calculations', 'status')) {
            unset($data['status']);
        }
        if (!Schema::hasColumn('calculations', 'offer_type')) {
            unset($data['offer_type']);
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
