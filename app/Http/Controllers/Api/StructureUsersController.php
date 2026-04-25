<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructureUserStoreRequest;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Structure\IdempotencyService;
use App\Services\Structure\StructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class StructureUsersController extends Controller
{
    public function store(
        StructureUserStoreRequest $request,
        TokenContext $context,
        StructureService $structure,
        IdempotencyService $idempotency
    ): JsonResponse {
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '') {
            return response()->json(['message' => 'Missing Idempotency-Key header.'], 400);
        }

        $payload = $request->validated();
        $payload['role'] = Str::upper($payload['role']);

        $actorId = $context->actorSupabaseId();
        if ($actorId === '') {
            return response()->json(['message' => 'Missing actor identity.'], 401);
        }

        $hash = $idempotency->hashPayload($payload);
        $existing = $idempotency->find($key);

        if ($existing) {
            if ($existing->actor_supabase_id !== $actorId || $existing->request_hash !== $hash) {
                return response()->json(['message' => 'Idempotency key conflict.'], 409);
            }

            if ($existing->status === 'completed') {
                return response()->json($existing->response_body ?? [], 200);
            }

            if ($existing->status === 'failed') {
                $existing->delete();
            } else {
            return response()->json(['message' => 'Request already in progress.'], 409);
            }
        }

        $record = $idempotency->start($key, $actorId, $hash);

        try {
            $result = $structure->createUser($payload, $context);
            $user = $result['user'];
            $response = $this->formatUser($user);
            $response['inviteSent'] = $result['invite_sent'];
            $response['inviteError'] = $result['invite_error'];
            $idempotency->complete($record, $response);

            return response()->json($response, 201);
        } catch (\Throwable $exception) {
            $idempotency->fail($record, $exception->getMessage());
            throw $exception;
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->supabase_id,
            'parentSupabaseId' => $user->parent_supabase_id,
            'hierarchicalCode' => $user->hierarchical_code,
            'hierarchicalId' => $user->hierarchical_code,
            'teamGroupPath' => $user->team_group_path,
            'role' => $user->role_cached,
            'contractStatus' => $user->contract_status,
            'rank' => $user->rank,
            'type' => $user->type,
            'addressData' => $user->address_json,
            'documents' => $user->documents_json,
            'isRemovedFromStructure' => (bool) $user->is_removed_from_structure,
            'renewalCommissionRate' => $user->renewal_commission_rate,
            'overrideCommissionRate' => $user->override_commission_rate,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'enabled' => $user->enabled,
        ];
    }
}
