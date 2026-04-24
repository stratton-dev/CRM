<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TokenContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages team group paths stored in users.team_group_path.
 * Replaces KeycloakTeamsController after Supabase migration.
 */
class StructureTeamsController extends Controller
{
    public function index(TokenContext $context): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $paths = User::query()
            ->whereNotNull('team_group_path')
            ->distinct()
            ->orderBy('team_group_path')
            ->pluck('team_group_path')
            ->values()
            ->all();

        return response()->json(['paths' => $paths]);
    }

    public function store(Request $request, TokenContext $context): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'code'         => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'display_name' => ['nullable', 'string', 'max:128'],
        ]);

        $root = rtrim(config('structure.teams_root', '/teams'), '/');
        $path = $root . '/' . strtoupper($data['code']);

        $exists = User::query()
            ->where('team_group_path', $path)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Zespół o tym kodzie już istnieje.'], 409);
        }

        return response()->json([
            'id'          => $path,
            'code'        => strtoupper($data['code']),
            'path'        => $path,
            'displayName' => $data['display_name'] ?? null,
        ], 201);
    }

    public function destroy(Request $request, TokenContext $context): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $affected = User::query()
            ->where('team_group_path', $data['path'])
            ->count();

        if ($affected > 0) {
            // Unassign users from this team path — do not delete the users themselves
            User::query()
                ->where('team_group_path', $data['path'])
                ->update(['team_group_path' => null]);
        }

        return response()->json([
            'id'   => $data['path'],
            'path' => $data['path'],
        ]);
    }
}
