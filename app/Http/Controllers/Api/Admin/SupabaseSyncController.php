<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TokenContext;
use Illuminate\Http\JsonResponse;

/**
 * Replaces KeycloakSyncController after Supabase migration.
 * User sync now happens automatically in SupabaseAuthenticate middleware on each login.
 * This endpoint provides current sync status from the local DB.
 */
class SupabaseSyncController extends Controller
{
    public function __invoke(TokenContext $context): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $total   = User::query()->count();
        $active  = User::query()->where('active', true)->whereNull('deleted_at')->count();
        $blocked = User::query()->where('is_blocked', true)->count();
        $removed = User::query()->where('is_removed_from_structure', true)->count();

        return response()->json([
            'status'   => 'ok',
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => $total,
            'errors'   => 0,
            'messages' => [
                'Synchronizacja odbywa się automatycznie przy każdym logowaniu przez Supabase.',
                "Użytkownicy w bazie: {$total} (aktywnych: {$active}, zablokowanych: {$blocked}, usuniętych ze struktury: {$removed})",
            ],
        ]);
    }
}
