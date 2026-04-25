<?php

namespace App\Events\Structure;

use App\Contracts\BroadcastsToReverb;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class StructureUserEvent implements BroadcastsToReverb
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $actorSupabaseId,
        public ?string $previousTeamId = null,
        public ?string $previousParentSupabaseId = null
    ) {
    }

    public function broadcastChannels(): array
    {
        $channels = [];
        $teamId = $this->user->team_id;

        if ($teamId) {
            $channels[] = 'team.'.$teamId;
        }

        if ($this->previousTeamId && $this->previousTeamId !== $teamId) {
            $channels[] = 'team.'.$this->previousTeamId;
        }

        $supabaseId = $this->user->supabase_id ?: (string) $this->user->id;
        if ($supabaseId !== '') {
            $channels[] = 'user.'.$supabaseId;
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(): array
    {
        return [
            'id' => (string) $this->user->supabase_id,
            'parentSupabaseId' => $this->user->parent_supabase_id,
            'hierarchicalCode' => $this->user->hierarchical_code,
            'teamId' => $this->user->team_id,
            'teamGroupPath' => $this->user->team_group_path,
            'role' => $this->user->role_cached,
            'contractStatus' => $this->user->contract_status,
            'rank' => $this->user->rank,
            'type' => $this->user->type,
            'isRemovedFromStructure' => (bool) $this->user->is_removed_from_structure,
            'enabled' => (bool) $this->user->enabled,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function basePayload(): array
    {
        return [
            'actorSupabaseId' => $this->actorSupabaseId,
            'user' => $this->userPayload(),
            'previousTeamId' => $this->previousTeamId,
            'previousParentSupabaseId' => $this->previousParentSupabaseId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadWithAction(string $action): array
    {
        return array_merge($this->basePayload(), ['action' => $action]);
    }
}
