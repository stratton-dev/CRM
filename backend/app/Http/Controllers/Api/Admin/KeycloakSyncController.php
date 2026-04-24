<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\TokenContext;
use App\Services\Keycloak\KeycloakSyncService;
use Illuminate\Http\JsonResponse;

class KeycloakSyncController extends Controller
{
    public function __invoke(TokenContext $context, KeycloakSyncService $sync): JsonResponse
    {
        if ($context->primaryRole() !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $report = $sync->withLock(fn () => $sync->syncAllUsers(true));
        $status = $report->status === 'locked' ? 429 : 200;

        return response()->json($report->toArray(), $status);
    }
}
