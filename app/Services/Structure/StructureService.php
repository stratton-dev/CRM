<?php

namespace App\Services\Structure;

use App\Events\Structure\StructureUserCreated;
use App\Events\Structure\StructureUserMoved;
use App\Events\Structure\StructureUserRemoved;
use App\Events\Structure\StructureUserRestored;
use App\Models\User;
use App\Services\Auth\SupabaseAdminService;
use App\Services\Auth\TokenContext;
use App\Services\Autenti\AutentiOnboardingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StructureService
{
    public function __construct(
        private readonly HierarchicalCodeService $codes,
        private readonly AutentiOnboardingService $autenti,
        private readonly SupabaseAdminService $supabaseAdmin
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
             $actor = User::query()->where('supabase_id', $context->actorSupabaseId())->first();
            if (!$actor) {
                return $query->whereRaw('1 = 0')->get();
            }

            // Using subtree traversal ensures Director sees all descendants regardless of team path
            $subtree = collect($this->collectSubtreeUsers($actor))
                ->filter(fn (User $user) => in_array($user->role_cached, ['DIRECTOR', 'MANAGER', 'SALES'], true))
                ->values();

            return $subtree;
        }

        if ($role === 'MANAGER') {
            $actor = User::query()->where('supabase_id', $context->actorSupabaseId())->first();
            if (!$actor) {
                return $query->whereRaw('1 = 0')->get();
            }

            $subtree = collect($this->collectSubtreeUsers($actor))
                ->filter(fn (User $user) => in_array($user->role_cached, ['MANAGER', 'SALES'], true))
                ->values();

            return $subtree;
        }

        if ($role === 'SALES') {
            return $query->where('supabase_id', $context->actorSupabaseId())->get();
        }

        return $query->whereRaw('1 = 0')->get();
    }

    public function createUser(array $data, TokenContext $context): array
    {
        $parent = null;
        if (!empty($data['parent_supabase_id'])) {
            $parent = User::query()
                ->where('supabase_id', $data['parent_supabase_id'])
                ->first();
            if (!$parent) {
                throw ValidationException::withMessages([
                    'parent_supabase_id' => ['Parent user not found.'],
                ]);
            }
        }

        $role = $data['role'];

        // LEADOWIEC, ADMIN and CLIENT_HR bypass the team-path + hierarchical-code
        // pipeline. Admins are global; CLIENT_HR is attached to an external
        // organization; LEADOWIEC chains under a sales rep without team scope.
        if (in_array($role, ['LEADOWIEC', 'ADMIN', 'CLIENT_HR'], true)) {
            $teamPath = $parent?->team_group_path ?? ($data['team_group_path'] ?? '');
            $teamId   = $parent?->team_id ?? null;
            try {
                Gate::authorize('structure.create', [$parent, $role, $teamPath]);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                // For ADMIN creating ADMIN/CLIENT_HR globally we accept lack of
                // team context — the gate is built around per-team hierarchies.
                if ($context->primaryRole() !== 'ADMIN') {
                    throw $e;
                }
            }
            $hierarchicalCode = null;
        } else {
            $teamPath = $this->resolveTeamPath($context, $parent, $data);
            $teamId   = $this->extractTeamId($teamPath);
            Gate::authorize('structure.create', [$parent, $role, $teamPath]);
            $parentCode       = $parent?->hierarchical_code;
            $hierarchicalCode = $this->codes->generate($teamPath, $parentCode, $this->initialsFromName($data['name'] ?? null));
        }

        $supabaseUuid = $data['supabase_id'] ?? null;
        $inviteSent = null;
        $inviteError = null;
        $inviteLink = null;
        $supabaseUserCreated = false;

        // Try to create the auth user in Supabase first so we anchor on the
        // returned UUID. Fall back to a locally-generated UUID if the admin
        // API is not configured or fails (record will still be created so
        // workflow doesn't break in dev environments).
        if (empty($data['skip_supabase_user']) && $this->supabaseAdmin->isConfigured() && !empty($data['email'])) {
            try {
                $supabaseUser = $this->supabaseAdmin->createUser([
                    'email' => $data['email'],
                    'password' => $data['password'] ?? Str::random(24),
                    'name' => $data['name'] ?? null,
                    'role' => $role,
                    'phone' => $data['phone'] ?? null,
                    'email_confirm' => true,
                ]);
                $supabaseUuid = $supabaseUser['id'];
                $supabaseUserCreated = true;

                if (!empty($data['send_password_reset'])) {
                    try {
                        $link = $this->supabaseAdmin->generatePasswordResetLink($data['email']);
                        $inviteLink = $link['action_link'];
                        $inviteSent = true;
                    } catch (\Throwable $e) {
                        $inviteSent = false;
                        $inviteError = $e->getMessage();
                        Log::warning('Supabase password reset link failed', ['email' => $data['email'], 'error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                $inviteSent = false;
                $inviteError = 'Supabase admin: ' . $e->getMessage();
                Log::error('Supabase createUser failed; falling back to local UUID', [
                    'email' => $data['email'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($supabaseUuid)) {
            $supabaseUuid = Str::uuid()->toString();
        }

        $user = User::create([
            'supabase_id' => $supabaseUuid,
            'parent_supabase_id' => $data['parent_supabase_id'] ?? null,
            'team_id' => $teamId,
            'team_group_path' => $teamPath ?: null,
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
            $this->autenti->startForUser($user, (array) $data['documents_json'], $context->actorSupabaseId());
        }

        event(new StructureUserCreated($user, $context->actorSupabaseId()));

        return [
            'user' => $user,
            'invite_sent' => $inviteSent,
            'invite_error' => $inviteError,
            'invite_link' => $inviteLink,
            'supabase_user_created' => $supabaseUserCreated,
        ];
    }

    /**
     * Trwale usuwa uzytkownika: z Supabase Auth (jesli ma supabase_id) i z DB.
     */
    public function deleteUser(string $userSupabaseId, TokenContext $context): array
    {
        $user = User::query()->where('supabase_id', $userSupabaseId)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'user_supabase_id' => ['User not found.'],
            ]);
        }

        if ($context->actorSupabaseId() !== '' && $context->actorSupabaseId() === $user->supabase_id) {
            throw ValidationException::withMessages([
                'user_supabase_id' => ['Cannot delete yourself.'],
            ]);
        }

        Gate::authorize('structure.remove', [$user]);

        $supabaseDeleted = false;
        $supabaseError = null;

        if ($this->supabaseAdmin->isConfigured() && $user->supabase_id) {
            try {
                $this->supabaseAdmin->deleteUser($user->supabase_id);
                $supabaseDeleted = true;
            } catch (\Throwable $e) {
                $supabaseError = $e->getMessage();
                Log::warning('Supabase deleteUser failed; deleting DB record anyway', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Detach children so we don't leave dangling parent_supabase_id pointers.
        User::query()
            ->where('parent_supabase_id', $user->supabase_id)
            ->update(['parent_supabase_id' => null]);

        $user->delete();

        return [
            'deleted' => true,
            'supabase_deleted' => $supabaseDeleted,
            'supabase_error' => $supabaseError,
        ];
    }

    /**
     * Wysyla link do resetu hasla przez Supabase.
     */
    public function sendPasswordReset(string $userSupabaseId): array
    {
        $user = User::query()->where('supabase_id', $userSupabaseId)->first();
        if (!$user || !$user->email) {
            throw ValidationException::withMessages([
                'user_supabase_id' => ['User or email not found.'],
            ]);
        }

        if (!$this->supabaseAdmin->isConfigured()) {
            throw new \RuntimeException('Supabase admin not configured.');
        }

        return $this->supabaseAdmin->generatePasswordResetLink($user->email);
    }

    public function restoreUser(string $userSupabaseId, TokenContext $context): array
    {
        $user = User::query()
            ->where('supabase_id', $userSupabaseId)
            ->firstOrFail();

        $teamPath = $user->team_group_path;
        $role = (string) ($user->role_cached ?? '');
        if (!$teamPath || $role === '') {
            throw ValidationException::withMessages([
                'team_group_path' => ['Missing team or role for restore.'],
            ]);
        }

        $newSupabaseUuid = $user->supabase_id;
        $inviteSent = null;
        $inviteError = null;
        $restored = true;
        $created = false;

        $result = \Illuminate\Support\Facades\DB::transaction(function () use (
            $user,
            $newSupabaseUuid,
            $inviteSent,
            $inviteError,
            $restored,
            $created
        ) {
            $oldSupabaseUuid = $user->supabase_id;
            $user->fill([
                'supabase_id' => $newSupabaseUuid,
                'is_removed_from_structure' => false,
                'enabled' => true,

            ])->save();

            if ($oldSupabaseUuid && $newSupabaseUuid && $oldSupabaseUuid !== $newSupabaseUuid) {
                User::query()
                    ->where('parent_supabase_id', $oldSupabaseUuid)
                    ->update(['parent_supabase_id' => $newSupabaseUuid]);
            }

            return [
                'user' => $user->refresh(),
                'invite_sent' => $inviteSent,
                'invite_error' => $inviteError,
                'restored' => $restored,
                'created' => $created,
            ];
        });

        event(new StructureUserRestored($result['user'], $context->actorSupabaseId()));

        return $result;
    }

    public function moveUser(
        string $userSupabaseId,
        ?string $newParentSupabaseId,
        TokenContext $context,
        ?string $newTeamGroupPath = null
    ): User
    {
        $user = User::query()
            ->where('supabase_id', $userSupabaseId)
            ->firstOrFail();

        $newParent = null;
        if ($newParentSupabaseId) {
            $newParent = User::query()
                ->where('supabase_id', $newParentSupabaseId)
                ->first();
            if (!$newParent) {
                throw ValidationException::withMessages([
                    'new_parent_supabase_id' => ['Parent user not found.'],
                ]);
            }
        }

        if ($newParent && $newParent->supabase_id === $user->supabase_id) {
            throw ValidationException::withMessages([
                'new_parent_supabase_id' => ['Cannot move user under itself.'],
            ]);
        }

        $this->ensureNoCycle($user, $newParent);

        $targetTeamPath = $newParent?->team_group_path ?? $newTeamGroupPath ?? $user->team_group_path;

        // Only validate team-path mismatch when both sides actually have a team.
        if ($newParent && $newParent->team_group_path && $targetTeamPath
            && $newParent->team_group_path !== $targetTeamPath) {
            throw ValidationException::withMessages([
                'new_team_group_path' => ['Parent team does not match target team.'],
            ]);
        }

        Gate::authorize('structure.move', [$user, $newParent, $targetTeamPath]);

        $previousTeamId = $user->team_id;
        $previousParentSupabaseId = $user->parent_supabase_id;
        $teamId = $targetTeamPath ? $this->extractTeamId($targetTeamPath) : null;
        $parentCode = $newParent?->hierarchical_code;
        $newCode = $targetTeamPath
            ? $this->codes->generate($targetTeamPath, $parentCode, $this->initialsFromName($user->name))
            : null;

        if ($targetTeamPath !== $user->team_group_path) {
            // team_group_path is updated in DB only (no external sync needed)
        }

        $movedUser = \Illuminate\Support\Facades\DB::transaction(function () use (
            $user,
            $targetTeamPath,
            $teamId,
            $newParent,
            $newCode
        ) {
            $this->updateSubtreeCodes($user, $targetTeamPath, $teamId, $newParent?->supabase_id, $newCode);
            return $user->refresh();
        });

        event(new StructureUserMoved(
            $movedUser,
            $context->actorSupabaseId(),
            $previousTeamId,
            $previousParentSupabaseId
        ));

        return $movedUser;
    }

    public function removeUser(string $userSupabaseId, TokenContext $context): User
    {
        $user = User::query()
            ->where('supabase_id', $userSupabaseId)
            ->firstOrFail();

        if ($context->actorSupabaseId() !== '' && $context->actorSupabaseId() === $user->supabase_id) {
            throw ValidationException::withMessages([
                'user_supabase_id' => ['Cannot remove yourself from structure.'],
            ]);
        }

        Gate::authorize('structure.remove', [$user]);

        $user->fill([
            'is_removed_from_structure' => true,
            'enabled' => false,
            'active' => false,
        ])->save();

        $removedUser = $user->refresh();

        event(new StructureUserRemoved($removedUser, $context->actorSupabaseId()));

        return $removedUser;
    }

    public function restoreTeamUsers(string $teamGroupPath, TokenContext $context): array
    {
        $users = User::query()
            ->where('team_group_path', $teamGroupPath)
            ->where(function ($query) {
                $query->where('is_removed_from_structure', true)
                    ->orWhere('enabled', false)
                    ->orWhere('active', false);
            })
            ->get();

        $restored = 0;
        $errors = [];
        foreach ($users as $user) {
            try {
                $this->restoreUser($user->supabase_id, $context);
                $restored++;
            } catch (\Throwable $exception) {
                $errors[] = [
                    'supabase_id' => $user->supabase_id,
                    'email' => $user->email,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'total' => $users->count(),
            'restored' => $restored,
            'errors' => $errors,
        ];
    }

    public function deleteRemovedTeamUsersFromDb(string $teamGroupPath): array
    {
        $users = User::query()
            ->where('team_group_path', $teamGroupPath)
            ->where('is_removed_from_structure', true)
            ->get();

        $total = $users->count();

        \Illuminate\Support\Facades\DB::transaction(function () use ($users): void {
            foreach ($users as $user) {
                $user->delete();
            }
        });

        return [
            'deleted' => $total,
        ];
    }

    private function resolveTeamPath(TokenContext $context, ?User $parent, array $data): string
    {
        if ($parent) {
            if (!$parent->team_group_path) {
                throw ValidationException::withMessages([
                    'parent_supabase_id' => ['Parent user has no team assigned.'],
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
            if ($cursor->supabase_id === $user->supabase_id) {
                throw ValidationException::withMessages([
                    'new_parent_supabase_id' => ['Cannot move user under a descendant.'],
                ]);
            }

            $cursor = $cursor->parent_supabase_id
                ? User::query()->where('supabase_id', $cursor->parent_supabase_id)->first()
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
        ?string $teamGroupPath,
        ?string $teamId,
        ?string $newParentSupabaseId,
        ?string $newCode
    ): void {
        $user->fill([
            'parent_supabase_id' => $newParentSupabaseId,
            'team_group_path' => $teamGroupPath,
            'team_id' => $teamId,
            'hierarchical_code' => $newCode,
            'hierarchical_id' => $newCode,
        ])->save();

        $children = User::query()
            ->where('parent_supabase_id', $user->supabase_id)
            ->get();

        foreach ($children as $child) {
            $childCode = ($teamGroupPath && $newCode)
                ? $this->codes->generate($teamGroupPath, $newCode, $this->initialsFromName($child->name))
                : null;
            $this->updateSubtreeCodes($child, $teamGroupPath, $teamId, $user->supabase_id, $childCode);
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
                ->where('parent_supabase_id', $current->supabase_id)
                ->get();

            foreach ($children as $child) {
                $queue[] = $child;
            }
        }

        return $items;
    }
}
