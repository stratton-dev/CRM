<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $user->load('role');

        // LEADOWIEC: auto-create direct conversation with opiekun on first access
        if ($user->role_cached === 'LEADOWIEC' && $user->leadowiec_opiekun_id) {
            $this->ensureLeadowiecChatConversation($user->id, $user->leadowiec_opiekun_id);
        }

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
            'leadowiecOpiekunId' => $user->leadowiec_opiekun_id,
            'leadowiecCommissionRate' => $user->leadowiec_commission_rate,
        ]);
    }

    private function ensureLeadowiecChatConversation(int $leadowiecId, int $opiekunId): void
    {
        $key = 'direct_' . min($leadowiecId, $opiekunId) . '_' . max($leadowiecId, $opiekunId);

        try {
            DB::transaction(function () use ($key, $leadowiecId, $opiekunId) {
                $exists = DB::table('chat_conversations')->where('key', $key)->exists();
                if ($exists) {
                    return;
                }

                $conversationId = DB::table('chat_conversations')->insertGetId([
                    'type'       => 'direct',
                    'key'        => $key,
                    'is_group'   => false,
                    'name'       => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('chat_participants')->insert([
                    [
                        'conversation_id' => $conversationId,
                        'user_id'         => $leadowiecId,
                        'last_read_at'    => now(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ],
                    [
                        'conversation_id' => $conversationId,
                        'user_id'         => $opiekunId,
                        'last_read_at'    => now(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ],
                ]);
            });
        } catch (\Exception $e) {
            // Duplicate key = conversation already created by concurrent request — ignore
            \Illuminate\Support\Facades\Log::debug('MeController: chat conversation already exists (race handled)', ['key' => $key]);
        }
    }
}
