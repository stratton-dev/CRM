<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmEventLog;
use Illuminate\Http\Request;

class CrmEventLogsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmEventLog::query()->with(['event:id,key,label', 'status:id,key,label', 'user:id,name']);
        if ($eventKey = $request->string('event_key')->toString()) {
            $q->where('event_key', $eventKey);
        }
        if ($statusId = $request->integer('status_id')) {
            $q->where('status_id', $statusId);
        }
        return $q->orderByDesc('occurred_at')->paginate($request->integer('per_page', 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'nullable|integer|exists:crm_events,id',
            'event_key' => 'required|string|max:100',
            'status_id' => 'nullable|integer|exists:crm_statuses,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'payload' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        $log = CrmEventLog::create($data);
        return response()->json($log, 201);
    }
}
