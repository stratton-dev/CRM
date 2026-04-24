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
            'client_id' => 'required',
            'user_id' => 'nullable',
            'user_keycloak_id' => 'nullable|string',
            'status' => 'nullable|string',
            'offer_status' => 'nullable|string',
            'valid_until' => 'nullable|date',
            'resume_at' => 'nullable|date',
        ]);

        $userId = $this->resolveUserId($data['user_id'] ?? null, $data['user_keycloak_id'] ?? null);

        if (!$userId && $request->user()) {
            $userId = $request->user()->id;
        }

        $meeting = Meeting::create([
            'client_id' => $data['client_id'],
            'user_id' => $userId,
            'status' => $data['status'] ?? 'open',
            'offer_status' => $data['offer_status'] ?? 'preparing',
            'valid_until' => $data['valid_until'] ?? now()->addDays(30),
            'resume_at' => $data['resume_at'] ?? null,
        ]);

        return response()->json($meeting, 201);
    }

    public function storeProspect(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'nullable|string|max:20',
            'contact_name' => 'required|string|max:255',
            'contact_phone' => 'nullable|string|max:64',
            'contact_email' => 'nullable|email|max:255',
            'contact_position' => 'nullable|string|max:255',
            'is_decision_maker' => 'nullable|boolean',
            'address' => 'nullable|string|max:500',
            'industry' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:50',
            'source' => 'required|string',
            'initial_meeting' => 'required|array',
            'initial_meeting.date' => 'required|date',
            'initial_meeting.notes' => 'nullable|string',
        ]);

        return \DB::transaction(function () use ($data, $request) {
            // 1. Create or Find Company
            $client = null;
            if (!empty($data['nip'])) {
                $client = \App\Models\Client::where('nip', $data['nip'])->first();
            }

            if (!$client) {
                // Parse address if possible
                $client = \App\Models\Client::create([
                    'name' => $data['name'],
                    'nip' => $data['nip'] ?? null,
                    'address_line1' => $data['address'] ?? 'Nie podano',
                    'postal_code' => '00-000', // Dummy or parse
                    'city' => 'Warszawa', // Dummy or parse
                ]);
            }

            // 2. Create CRM Profile
            $profile = $client->crmProfile()->updateOrCreate([], [
                'status' => 'IN_TALKS',
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'owner_user_id' => $request->user()->id,
                'source' => $data['source'],
                'is_decision_maker' => $data['is_decision_maker'] ?? false,
                'industry' => $data['industry'] ?? null,
                'company_size' => $data['company_size'] ?? null,
                'contact_position' => $data['contact_position'] ?? null,
            ]);

            // 3. Create Activity
            $client->activities()->create([
                'type' => 'MEETING',
                'description' => $data['initial_meeting']['notes'] ?? 'Pierwsze spotkanie prospect',
                'occurred_at' => $data['initial_meeting']['date'],
                'user_id' => $request->user()->id,
            ]);

            // 4. Create Meeting Session (optional but requested by current model)
            $meeting = $client->meetings()->create([
                'user_id' => $request->user()->id,
                'status' => 'open',
                'valid_until' => now()->addDays(30),
            ]);

            return response()->json([
                'client_id' => $client->id,
                'meeting_id' => $meeting->id,
            ], 201);
        });
    }

    public function update(Request $request, Meeting $meeting)
    {
        $data = $request->validate([
            'client_id' => 'sometimes|exists:companies,id',
            'user_id' => 'nullable',
            'user_keycloak_id' => 'nullable|string',
            'status' => 'nullable|in:open,completed,expired',
            'calculation_shown' => 'nullable|boolean',
            'offer_status' => 'nullable|in:preparing,generated,sent',
            'valid_until' => 'nullable|date',
            'paused_at' => 'nullable|date',
            'resume_at' => 'nullable|date',
        ]);
        $this->authorize('update', $meeting);
        if (array_key_exists('user_id', $data) || array_key_exists('user_keycloak_id', $data)) {
            $resolved = $this->resolveUserId($data['user_id'] ?? null, $data['user_keycloak_id'] ?? null);
            unset($data['user_keycloak_id']);
            if (!$resolved) {
                return response()->json(['message' => 'User not found.'], 422);
            }
            $data['user_id'] = $resolved;
        } else {
            unset($data['user_keycloak_id']);
        }
        $meeting->update($data);
        return $meeting->refresh();
    }

    public function destroy(Meeting $meeting)
    {
        $meeting->delete();
        return response()->noContent();
    }

    private function resolveUserId($userId, $userKeycloakId): ?int
    {
        if ($userKeycloakId) {
            $user = User::query()->where('keycloak_id', $userKeycloakId)->first();
            if ($user) {
                return $user->id;
            }
        }

        if ($userId === null || $userId === '') {
            return null;
        }

        if (is_numeric($userId)) {
            return (int) $userId;
        }

        $user = User::query()->where('keycloak_id', (string) $userId)->first();
        return $user?->id;
    }
}
