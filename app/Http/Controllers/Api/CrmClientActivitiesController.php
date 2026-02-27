<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmClientActivity;
use App\Models\User;
use Illuminate\Http\Request;

class CrmClientActivitiesController extends Controller
{
    public function index(Request $request)
    {
        $q = CrmClientActivity::query()->with(['client:id,name', 'user:id,name,keycloak_id']);
        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        if ($userId = $this->resolveUserId($request->input('user_id'))) {
            $q->where('user_id', $userId);
        }
        if ($type = $request->string('type')->toString()) {
            $q->where('type', $type);
        }
        return $q->orderByDesc('occurred_at')->paginate($request->integer('per_page', 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'user_id' => 'required',
            'type' => 'required|in:CALL,MEETING,EMAIL,NOTE',
            'description' => 'required|string',
            'occurred_at' => 'required|date',
            'is_completed' => 'nullable|boolean',
        ]);

        $userId = $this->resolveUserId($data['user_id']);
        if (!$userId) {
            return response()->json(['message' => 'User not found.'], 422);
        }

        $activity = CrmClientActivity::create([
            'client_id' => $data['client_id'],
            'user_id' => $userId,
            'type' => $data['type'],
            'description' => $data['description'],
            'occurred_at' => $data['occurred_at'],
            'is_completed' => $data['is_completed'] ?? false,
        ]);

        return response()->json($activity->load(['client:id,name', 'user:id,name,keycloak_id']), 201);
    }

    public function update(Request $request, CrmClientActivity $activity)
    {
        $data = $request->validate([
            'type' => 'sometimes|required|in:CALL,MEETING,EMAIL,NOTE',
            'description' => 'sometimes|required|string',
            'occurred_at' => 'sometimes|required|date',
            'is_completed' => 'nullable|boolean',
        ]);

        $activity->update($data);
        return $activity->refresh()->load(['client:id,name', 'user:id,name,keycloak_id']);
    }

    public function destroy(CrmClientActivity $crmClientActivity)
    {
        $crmClientActivity->delete();
        return response()->noContent();
    }

    private function resolveUserId($value): ?int
    {
        if (!$value) return null;
        $query = User::query();
        if (is_numeric($value)) {
            $query->where('id', (int) $value)->orWhere('keycloak_id', (string) $value);
        } else {
            $query->where('keycloak_id', (string) $value);
        }
        $u = $query->first();
        return $u ? $u->id : null;
    }
}
