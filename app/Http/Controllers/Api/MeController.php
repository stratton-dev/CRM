<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user->load('role');

        return response()->json([
            'id' => $user->supabase_id ?? (string) $user->id,
            'supabase_id' => $user->supabase_id,
            'organization_id' => $user->organization_id,
            'role_id' => $user->role_id,
            'role' => $user->role_cached ?: $user->role?->code,
            'role_name' => $user->role?->name,
            'teamId' => $user->team_id,
            'teamGroupPath' => $user->team_group_path,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'hierarchicalId' => $user->hierarchical_id,
            'hierarchicalCode' => $user->hierarchical_code,
            'crmNumber' => $user->crm_number,
            'rank' => $user->rank,
            'points' => $user->points,
            'active' => (bool) $user->active,
        ]);
    }
}
