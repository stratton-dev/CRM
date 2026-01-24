<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KeycloakTeamStoreRequest;
use App\Services\Auth\TokenContext;
use App\Services\Keycloak\KeycloakTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KeycloakTeamsController extends Controller
{
    public function index(
        TokenContext $context,
        KeycloakTeamService $teams,
        \Illuminate\Http\Request $request
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $paths = $request->boolean('refresh') ? $teams->refreshTeams() : $teams->listTeams();
        return response()->json(['paths' => $paths]);
    }

    public function store(
        KeycloakTeamStoreRequest $request,
        TokenContext $context,
        KeycloakTeamService $teams
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validated();

        try {
            $result = $teams->createTeam($data['code'], $data['display_name'] ?? null);
            Log::channel('keycloak')->info('Keycloak team created', [
                'actor_keycloak_id' => $context->actorKeycloakId(),
                'team' => $result,
            ]);

            return response()->json($result, 201);
        } catch (RuntimeException $exception) {
            Log::channel('keycloak')->warning('Keycloak team creation failed', [
                'actor_keycloak_id' => $context->actorKeycloakId(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }

    public function destroy(
        \Illuminate\Http\Request $request,
        TokenContext $context,
        KeycloakTeamService $teams
    ): JsonResponse {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        try {
            $result = $teams->deleteTeam($data['path']);
            Log::channel('keycloak')->info('Keycloak team deleted', [
                'actor_keycloak_id' => $context->actorKeycloakId(),
                'team' => $result,
            ]);

            return response()->json($result);
        } catch (RuntimeException $exception) {
            Log::channel('keycloak')->warning('Keycloak team delete failed', [
                'actor_keycloak_id' => $context->actorKeycloakId(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }
}
