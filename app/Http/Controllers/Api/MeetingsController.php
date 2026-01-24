<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Structure\StructureService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class MeetingsController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TokenContext $context, StructureService $structure)
    {
        $q = Meeting::query()->with(['client:id,name', 'user:id,name,keycloak_id']);
        $role = $context->primaryRole();
        $userIds = [];
        if ($role !== 'ADMIN') {
            $userIds = $structure->listUsers($context)->pluck('id')->all();
            if (!$userIds) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereIn('user_id', $userIds);
            }
        }

        if ($clientId = $request->integer('client_id')) {
            $q->where('client_id', $clientId);
        }
        if ($userId = $request->integer('user_id')) {
            if ($role === 'ADMIN' || in_array($userId, $userIds, true)) {
                $q->where('user_id', $userId);
            } else {
                $q->whereRaw('1 = 0');
            }
        }
        if ($status = $request->string('status')->toString()) {
            $q->where('status', $status);
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);
        return $meeting->load(['client:id,name', 'user:id,name,keycloak_id', 'analysis', 'calculations']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
            'user_keycloak_id' => 'nullable|string',
            'status' => 'nullable|in:open,completed,expired',
            'calculation_shown' => 'nullable|boolean',
            'offer_status' => 'nullable|in:preparing,generated,sent',
            'valid_until' => 'required|date',
            'paused_at' => 'nullable|date',
            'resume_at' => 'nullable|date',
        ]);
        if (empty($data['user_id']) && !empty($data['user_keycloak_id'])) {
            $user = User::query()->where('keycloak_id', $data['user_keycloak_id'])->first();
            if ($user) {
                $data['user_id'] = $user->id;
            }
        }
        unset($data['user_keycloak_id']);
        if (empty($data['user_id'])) {
            return response()->json(['message' => 'User not found.'], 422);
        }
        $existing = Meeting::query()
            ->where('client_id', $data['client_id'])
            ->where('status', 'open')
            ->first();
        if ($existing) {
            return response()->json(
                ['message' => 'Client already has an open meeting.', 'meeting_id' => $existing->id],
                409
            );
        }
        $meeting = Meeting::create($data);
        return response()->json($meeting, 201);
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'client_id' => 'sometimes|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
            'user_keycloak_id' => 'nullable|string',
            'status' => 'nullable|in:open,completed,expired',
            'calculation_shown' => 'nullable|boolean',
            'offer_status' => 'nullable|in:preparing,generated,sent',
            'valid_until' => 'nullable|date',
            'paused_at' => 'nullable|date',
            'resume_at' => 'nullable|date',
        ]);
        if (empty($data['user_id']) && !empty($data['user_keycloak_id'])) {
            $user = User::query()->where('keycloak_id', $data['user_keycloak_id'])->first();
            if ($user) {
                $data['user_id'] = $user->id;
            }
        }
        unset($data['user_keycloak_id']);
        $meeting->update($data);
        return $meeting->refresh();
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();
        return response()->noContent();
    }
}
