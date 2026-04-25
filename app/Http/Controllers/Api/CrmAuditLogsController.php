<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class CrmAuditLogsController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmAuditLog::query()->with('actor:id,supabase_id,name,email');

        if ($actorId = $this->resolveUserId($request->input('actor_id'))) {
            $q->where('actor_user_id', $actorId);
        }
        if ($action = $request->string('action')->toString()) {
            $q->where('action', $action);
        }
        if ($targetId = $request->string('target_id')->toString()) {
            $q->where('target_id', $targetId);
        }

        return $q->latest()->paginate($request->integer('per_page', 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'actor_id' => 'required',
            'action' => 'required|string|max:255',
            'target_id' => 'nullable|string|max:255',
            'details' => 'required|string',
        ]);

        $actorId = $this->resolveUserId($data['actor_id']);
        if (!$actorId) {
            return response()->json(['message' => 'Actor user not found.'], 422);
        }

        $log = CrmAuditLog::create([
            'actor_user_id' => $actorId,
            'action' => $data['action'],
            'target_id' => $data['target_id'] ?? null,
            'details' => $data['details'],
        ]);

        return response()->json($log->load('actor:id,supabase_id,name,email'), 201);
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
