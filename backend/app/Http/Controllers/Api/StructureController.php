<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StructureMoveRequest;
use App\Http\Requests\StructureRemoveRequest;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Keycloak\KeycloakSyncService;
use App\Services\Structure\StructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class StructureController extends Controller
{
    public function index(
        Request $request,
        TokenContext $context,
        StructureService $structure,
        KeycloakSyncService $sync
    ): JsonResponse
    {
        $shouldSync = $request->boolean('sync') && $context->primaryRole() === 'ADMIN';
        if ($shouldSync) {
            try {
                $report = $sync->withLock(fn () => $sync->syncAllUsers(true));
                Log::channel('keycloak')->info('Keycloak sync completed', [
                    'actor_keycloak_id' => $context->actorKeycloakId(),
                    'report' => $report instanceof \JsonSerializable ? $report->jsonSerialize() : $report,
                ]);
            } catch (\Throwable $e) {
                Log::channel('keycloak')->error('Keycloak sync failed', [
                    'actor_keycloak_id' => $context->actorKeycloakId(),
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $users = $structure->listUsers($context);

        return response()->json(
            $users->map(fn (User $user) => $this->formatUser($user))->values()
        );
    }

    public function move(
        StructureMoveRequest $request,
        TokenContext $context,
        StructureService $structure
    ): JsonResponse {
        $data = $request->validated();
        $user = $structure->moveUser(
            $data['user_keycloak_id'],
            $data['new_parent_keycloak_id'] ?? null,
            $context,
            $data['new_team_group_path'] ?? null
        );

        return response()->json($this->formatUser($user));
    }

    public function remove(
        StructureRemoveRequest $request,
        TokenContext $context,
        StructureService $structure
    ): JsonResponse {
        $data = $request->validated();
        $user = $structure->removeUser($data['user_keycloak_id'], $context);
        Log::channel('users')->info('Structure user deactivated', [
            'actor_keycloak_id' => $context->actorKeycloakId(),
            'user_keycloak_id' => $user->keycloak_id,
            'user_email' => $user->email,
        ]);

        return response()->json($this->formatUser($user));
    }

    public function restore(
        Request $request,
        TokenContext $context,
        StructureService $structure
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'user_keycloak_id' => ['required', 'string'],
        ]);

        $result = $structure->restoreUser($data['user_keycloak_id'], $context);
        $user = $result['user'];
        $response = $this->formatUser($user);
        $response['inviteSent'] = $result['invite_sent'] ?? null;
        $response['inviteError'] = $result['invite_error'] ?? null;
        $response['restored'] = $result['restored'] ?? false;
        $response['created'] = $result['created'] ?? false;

        return response()->json($response);
    }

    public function restoreTeam(
        Request $request,
        TokenContext $context,
        StructureService $structure
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'team_group_path' => ['required', 'string'],
        ]);

        $result = $structure->restoreTeamUsers($data['team_group_path'], $context);

        return response()->json($result);
    }

    public function deleteRemovedTeamUsersFromDb(
        Request $request,
        TokenContext $context,
        StructureService $structure
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'team_group_path' => ['required', 'string'],
        ]);

        try {
            $result = $structure->deleteRemovedTeamUsersFromDb($data['team_group_path']);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function regenerateCodes(Request $request, TokenContext $context): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $resetCounters = $request->boolean('reset_counters', true);
        $exitCode = Artisan::call('structure:regenerate-codes', [
            '--reset-counters' => $resetCounters,
        ]);

        $output = trim((string) Artisan::output());
        Log::channel('users')->info('Structure codes regenerated', [
            'actor_keycloak_id' => $context->actorKeycloakId(),
            'exit_code' => $exitCode,
        ]);

        return response()->json([
            'status' => $exitCode === 0 ? 'ok' : 'error',
            'exitCode' => $exitCode,
            'output' => $output,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->keycloak_id,
            'parentKeycloakId' => $user->parent_keycloak_id,
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
