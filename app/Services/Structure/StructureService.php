<?php

namespace App\Services\Structure;

use App\Events\Structure\StructureUserCreated;
use App\Events\Structure\StructureUserMoved;
use App\Events\Structure\StructureUserRemoved;
use App\Events\Structure\StructureUserRestored;
use App\Models\User;
use App\Services\Auth\TokenContext;
use App\Services\Autenti\AutentiOnboardingService;
use App\Services\Keycloak\KeycloakProvisioningService;
use App\Services\Keycloak\KeycloakTeamService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StructureService
{
    public function __construct(
        private readonly HierarchicalCodeService $codes,
        private readonly KeycloakTeamService $teams,
        private readonly KeycloakProvisioningService $provisioning,
        private readonly AutentiOnboardingService $autenti
    )
    {
    }

    public function listUsers(TokenContext $context)
    {
        $role = $context->primaryRole();
        $query = User::query();

        if (!$role) {
            return $query->whereRaw('1 = 0')->get();
        }

        if ($role === 'ADMIN') {
            return $query->get();
        }

        $teamPath = $context->teamGroupPath();
        if (!$teamPath) {
            return $query->whereRaw('1 = 0')->get();
        }

        if ($role === 'DIRECTOR') {
            return $query->where('team_group_path', $teamPath)
                ->whereIn('role_cached', ['DIRECTOR', 'MANAGER', 'SALES'])
                ->get();
        }

        if ($role === 'MANAGER') {
            $actor = User::query()->where('keycloak_id', $context->actorKeycloakId())->first();
            if (!$actor) {
                return $query->whereRaw('1 = 0')->get();
            }

            $subtree = collect($this->collectSubtreeUsers($actor))
                ->filter(fn (User $user) => in_array($user->role_cached, ['MANAGER', 'SALES'], true))
                ->values();

            return $subtree;
        }

        if ($role === 'SALES') {
            return $query->where('keycloak_id', $context->actorKeycloakId())->get();
        }

        return $query->whereRaw('1 = 0')->get();
    }

    public function createUser(array $data, TokenContext $context): array
    {
        $parent = null;
        if (!empty($data['parent_keycloak_id'])) {
            $parent = User::query()
                ->where('keycloak_id', $data['parent_keycloak_id'])
                ->first();
            if (!$parent) {
                throw ValidationException::withMessages([
                    'parent_keycloak_id' => ['Parent user not found.'],
                ]);
            }
        }

        $teamPath = $this->resolveTeamPath($context, $parent, $data);
        $role = $data['role'];
        $teamId = $this->extractTeamId($teamPath);

        Gate::authorize('structure.create', [$parent, $role, $teamPath]);

        $parentCode = $parent?->hierarchical_code;
        $hierarchicalCode = $this->codes->generate($teamPath, $parentCode, $this->initialsFromName($data['name'] ?? null));
        $keycloakId = $data['keycloak_id'] ?? null;
        $inviteSent = null;
        $inviteError = null;
        if (!$keycloakId && config('keycloak.sync_enabled')) {
            $provisioned = $this->provisioning->provisionUser($data, $teamPath, $role);
            $keycloakId = $provisioned['id'] ?? null;
            $inviteSent = $provisioned['invite_sent'] ?? null;
            $inviteError = $provisioned['invite_error'] ?? null;
        }
        $keycloakId = $keycloakId ?: Str::uuid()->toString();

        $user = User::create([
            'keycloak_id' => $keycloakId,
            'parent_keycloak_id' => $data['parent_keycloak_id'] ?? null,
            'team_id' => $teamId,
            'team_group_path' => $teamPath,
            'role_cached' => $role,
            'hierarchical_code' => $hierarchicalCode,
            'hierarchical_id' => $hierarchicalCode,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'contract_status' => $data['contract_status'] ?? null,
            'type' => $data['type'] ?? null,
            'address_json' => $data['address_json'] ?? null,
            'documents_json' => $data['documents_json'] ?? null,
            'is_removed_from_structure' => false,
            'active' => true,
            'enabled' => true,
            'password' => $data['password'] ?? Str::random(32),
        ]);

        if (config('autenti.enabled') && !empty($data['documents_json'])) {
            $this->autenti->startForUser($user, (array) $data['documents_json'], $context->actorKeycloakId());
        }

        event(new StructureUserCreated($user, $context->actorKeycloakId()));

        return [
            'user' => $user,
            'invite_sent' => $inviteSent,
            'invite_error' => $inviteError,
        ];
    }

    public function restoreUser(string $userKeycloakId, TokenContext $context): array
    {
        $user = User::query()
            ->where('keycloak_id', $userKeycloakId)
            ->firstOrFail();

        $teamPath = $user->team_group_path;
        $role = (string) ($user->role_cached ?? '');
        if (!$teamPath || $role === '') {
            throw ValidationException::withMessages([
                'team_group_path' => ['Missing team or role for restore.'],
            ]);
        }

        $result = $this->provisioning->restoreUser($user, $teamPath, $role);
        $newKeycloakId = $result['id'] ?? $user->keycloak_id;
        $inviteSent = $result['invite_sent'] ?? null;
        $inviteError = $result['invite_error'] ?? null;
        $restored = $result['restored'] ?? false;
        $created = $result['created'] ?? false;

        $result = \Illuminate\Support\Facades\DB::transaction(function () use (
            $user,
            $newKeycloakId,
            $inviteSent,
            $inviteError,
            $restored,
            $created
        ) {
            $oldKeycloakId = $user->keycloak_id;
            $user->fill([
                'keycloak_id' => $newKeycloakId,
                'is_removed_from_structure' => false,
                'enabled' => true,
                'sync_error' => null,
            ])->save();

            if ($oldKeycloakId && $newKeycloakId && $oldKeycloakId !== $newKeycloakId) {
                User::query()
                    ->where('parent_keycloak_id', $oldKeycloakId)
                    ->update(['parent_keycloak_id' => $newKeycloakId]);
            }

            return [
                'user' => $user->refresh(),
                'invite_sent' => $inviteSent,
                'invite_error' => $inviteError,
                'restored' => $restored,
                'created' => $created,
            ];
        });

        event(new StructureUserRestored($result['user'], $context->actorKeycloakId()));

        return $result;
    }

    public function moveUser(
        string $userKeycloakId,
        ?string $newParentKeycloakId,
        TokenContext $context,
        ?string $newTeamGroupPath = null
    ): User
    {
        $user = User::query()
            ->where('keycloak_id', $userKeycloakId)
            ->firstOrFail();

        $newParent = null;
        if ($newParentKeycloakId) {
            $newParent = User::query()
                ->where('keycloak_id', $newParentKeycloakId)
                ->first();
            if (!$newParent) {
                throw ValidationException::withMessages([
                    'new_parent_keycloak_id' => ['Parent user not found.'],
                ]);
            }
        }

        if ($newParent && $newParent->keycloak_id === $user->keycloak_id) {
            throw ValidationException::withMessages([
                'new_parent_keycloak_id' => ['Cannot move user under itself.'],
            ]);
        }

        $this->ensureNoCycle($user, $newParent);

        $targetTeamPath = $newParent?->team_group_path ?? $newTeamGroupPath ?? $user->team_group_path;
        if (!$targetTeamPath) {
            throw ValidationException::withMessages([
                'new_team_group_path' => ['Missing team group path for move.'],
            ]);
        }
        if ($newParent && $newParent->team_group_path !== $targetTeamPath) {
            throw ValidationException::withMessages([
                'new_team_group_path' => ['Parent team does not match target team.'],
            ]);
        }

        Gate::authorize('structure.move', [$user, $newParent, $targetTeamPath]);

        $previousTeamId = $user->team_id;
        $previousParentKeycloakId = $user->parent_keycloak_id;
        $teamId = $this->extractTeamId($targetTeamPath);
        $parentCode = $newParent?->hierarchical_code;
        $newCode = $this->codes->generate($targetTeamPath, $parentCode, $this->initialsFromName($user->name));

        if ($targetTeamPath !== $user->team_group_path && config('keycloak.sync_enabled')) {
            $subtree = $this->collectSubtreeUsers($user);
            try {
                foreach ($subtree as $member) {
                    $this->teams->moveUserToTeam($member->keycloak_id, $targetTeamPath);
                }
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'new_team_group_path' => ['Keycloak team update failed: '.$exception->getMessage()],
                ]);
            }
        }

        $movedUser = \Illuminate\Support\Facades\DB::transaction(function () use (
            $user,
            $targetTeamPath,
            $teamId,
            $newParent,
            $newCode
        ) {
            $this->updateSubtreeCodes($user, $targetTeamPath, $teamId, $newParent?->keycloak_id, $newCode);
            return $user->refresh();
        });

        event(new StructureUserMoved(
            $movedUser,
            $context->actorKeycloakId(),
            $previousTeamId,
            $previousParentKeycloakId
        ));

        return $movedUser;
    }

    public function removeUser(string $userKeycloakId, TokenContext $context): User
    {
        $user = User::query()
            ->where('keycloak_id', $userKeycloakId)
            ->firstOrFail();

        if ($context->actorKeycloakId() !== '' && $context->actorKeycloakId() === $user->keycloak_id) {
            throw ValidationException::withMessages([
                'user_keycloak_id' => ['Cannot remove yourself from structure.'],
            ]);
        }

        Gate::authorize('structure.remove', [$user]);

        if (config('keycloak.sync_enabled')) {
            try {
                $this->provisioning->deleteUser($user);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'user_keycloak_id' => ['Keycloak deletion failed: '.$exception->getMessage()],
                ]);
            }
        }

        $user->fill([
            'is_removed_from_structure' => true,
            'enabled' => false,
            'active' => false,
        ])->save();

        $removedUser = $user->refresh();

        event(new StructureUserRemoved($removedUser, $context->actorKeycloakId()));

        return $removedUser;
    }

    private function resolveTeamPath(TokenContext $context, ?User $parent, array $data): string
    {
        if ($parent) {
            if (!$parent->team_group_path) {
                throw ValidationException::withMessages([
                    'parent_keycloak_id' => ['Parent user has no team assigned.'],
                ]);
            }

            return $parent->team_group_path;
        }

        if (($context->primaryRole() === 'ADMIN') && !empty($data['team_group_path'])) {
            return $data['team_group_path'];
        }

        $actorTeam = $context->teamGroupPath();
        if (!$actorTeam) {
            throw ValidationException::withMessages([
                'team_group_path' => ['Missing team group in token.'],
            ]);
        }

        return $actorTeam;
    }

    private function ensureNoCycle(User $user, ?User $newParent): void
    {
        $cursor = $newParent;
        while ($cursor) {
            if ($cursor->keycloak_id === $user->keycloak_id) {
                throw ValidationException::withMessages([
                    'new_parent_keycloak_id' => ['Cannot move user under a descendant.'],
                ]);
            }

            $cursor = $cursor->parent_keycloak_id
                ? User::query()->where('keycloak_id', $cursor->parent_keycloak_id)->first()
                : null;
        }
    }

    private function extractTeamId(string $teamGroupPath): ?string
    {
        $parts = array_values(array_filter(explode('/', $teamGroupPath)));
        return $parts ? $parts[count($parts) - 1] : null;
    }

    private function updateSubtreeCodes(
        User $user,
        string $teamGroupPath,
        ?string $teamId,
        ?string $newParentKeycloakId,
        string $newCode
    ): void {
        $user->fill([
            'parent_keycloak_id' => $newParentKeycloakId,
            'team_group_path' => $teamGroupPath,
            'team_id' => $teamId,
            'hierarchical_code' => $newCode,
            'hierarchical_id' => $newCode,
        ])->save();

        $children = User::query()
            ->where('parent_keycloak_id', $user->keycloak_id)
            ->get();

        foreach ($children as $child) {
            $childCode = $this->codes->generate($teamGroupPath, $newCode, $this->initialsFromName($child->name));
            $this->updateSubtreeCodes($child, $teamGroupPath, $teamId, $user->keycloak_id, $childCode);
        }
    }

    private function initialsFromName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'XX';
        }

        $parts = array_values(array_filter(preg_split('/\s+/', $name)));
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, 2));
        }

        $initials = '';
        foreach ($parts as $part) {
            $initials .= mb_substr($part, 0, 1);
        }

        return mb_strtoupper($initials);
    }

    /**
     * @return array<int, User>
     */
    private function collectSubtreeUsers(User $root): array
    {
        $items = [];
        $queue = [$root];

        while ($queue) {
            /** @var User $current */
            $current = array_shift($queue);
            $items[] = $current;

            $children = User::query()
                ->where('parent_keycloak_id', $current->keycloak_id)
                ->get();

            foreach ($children as $child) {
                $queue[] = $child;
            }
        }

        return $items;
    }
}
