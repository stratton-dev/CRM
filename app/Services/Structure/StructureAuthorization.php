<?php

namespace App\Services\Structure;

use App\Models\User;
use App\Services\Auth\TokenContext;
use Illuminate\Support\Str;

class StructureAuthorization
{
    private const ROLE_LEVELS = [
        'ADMIN' => 0,
        'DIRECTOR' => 1,
        'MANAGER' => 2,
        'SALES' => 3,
    ];

    public function canCreate(TokenContext $context, ?User $parent, string $targetRole, string $targetTeamPath): bool
    {
        $actorRole = $this->normalizeRole($context->primaryRole());
        $targetRole = $this->normalizeRole($targetRole);

        if (!$actorRole || !$targetRole) {
            return false;
        }

        if ($actorRole !== 'ADMIN' && $targetRole !== 'LEADOWIEC') {
            $actorTeam = $context->teamGroupPath();
            if (!$actorTeam || $actorTeam !== $targetTeamPath) {
                return false;
            }
        }

        if (!$this->isAllowedCreateRole($actorRole, $targetRole)) {
            return false;
        }

        return $this->parentRoleAllows($parent, $targetRole, $targetTeamPath);
    }

    public function canMove(TokenContext $context, User $target, ?User $newParent, ?string $targetTeamPath): bool
    {
        $actorRole = $this->normalizeRole($context->primaryRole());
        $targetRole = $this->normalizeRole($target->role_cached);

        if (!$actorRole || !$targetRole || $targetRole === 'ADMIN') {
            return false;
        }

        // ADMIN can move anyone, regardless of team configuration.
        if ($actorRole === 'ADMIN') {
            return true;
        }

        $targetTeamPath = $targetTeamPath ?: $target->team_group_path;
        if (!$targetTeamPath) {
            return false;
        }

        if ($actorRole === 'DIRECTOR' && $targetRole === 'SALES' && !$newParent) {
            return true;
        }

        if ($actorRole !== 'ADMIN') {
            $actorTeam = $context->teamGroupPath();
            if (!$actorTeam || $target->team_group_path !== $actorTeam) {
                return false;
            }
            if ($newParent && $newParent->team_group_path !== $actorTeam) {
                return false;
            }
            if ($targetTeamPath !== $actorTeam) {
                return false;
            }
        }

        if (!$this->parentRoleAllows($newParent, $targetRole, $targetTeamPath)) {
            return false;
        }

        return $this->isAllowedMoveRole($actorRole, $targetRole);
    }

    public function canRemove(TokenContext $context, User $target): bool
    {
        $actorRole = $this->normalizeRole($context->primaryRole());
        $targetRole = $this->normalizeRole($target->role_cached);

        if (!$actorRole || !$targetRole || $targetRole === 'ADMIN') {
            return false;
        }

        if ($actorRole === 'ADMIN') {
            return true;
        }

        $actorTeam = $context->teamGroupPath();
        if (!$actorTeam || $target->team_group_path !== $actorTeam) {
            return false;
        }

        return in_array($actorRole, ['DIRECTOR'], true) && in_array($targetRole, ['MANAGER', 'SALES'], true);
    }

    private function isAllowedCreateRole(string $actorRole, string $targetRole): bool
    {
        // Any hierarchy role can add a Leadowiec — including another Leadowiec (infinite chain).
        if ($targetRole === 'LEADOWIEC') {
            return in_array($actorRole, ['ADMIN', 'DIRECTOR', 'MANAGER', 'SALES', 'LEADOWIEC'], true);
        }
        if ($actorRole === 'ADMIN') {
            return in_array($targetRole, ['DIRECTOR', 'MANAGER', 'SALES'], true);
        }
        if ($actorRole === 'DIRECTOR') {
            return $targetRole === 'MANAGER';
        }
        if ($actorRole === 'MANAGER') {
            return $targetRole === 'SALES';
        }

        return false;
    }

    private function isAllowedMoveRole(string $actorRole, string $targetRole): bool
    {
        if ($actorRole === 'ADMIN') {
            return true;
        }

        if ($actorRole === 'DIRECTOR') {
            return $targetRole === 'MANAGER';
        }

        if ($actorRole === 'MANAGER') {
            return $targetRole === 'SALES';
        }

        return false;
    }

    private function parentRoleAllows(?User $parent, string $targetRole, ?string $targetTeamPath): bool
    {
        if ($targetRole === 'DIRECTOR') {
            if (!$parent) {
                return true;
            }

            return $this->normalizeRole($parent->role_cached) === 'ADMIN'
                && $parent->team_group_path === $targetTeamPath;
        }

        if (!$parent) {
            return false;
        }

        $parentRole = $this->normalizeRole($parent->role_cached);
        if ($targetRole === 'MANAGER') {
            return in_array($parentRole, ['DIRECTOR', 'MANAGER'], true)
                && $parent->team_group_path === $targetTeamPath;
        }

        if ($targetRole === 'SALES') {
            if (!$parent) {
                return (bool) $targetTeamPath;
            }
            return $parentRole === 'MANAGER' && $parent->team_group_path === $targetTeamPath;
        }

        if ($targetRole === 'LEADOWIEC') {
            // Leadowiec only requires a parent of the right role; no team-path match needed.
            // LEADOWIEC parent is allowed so the chain can extend infinitely.
            if (!$parent) {
                return false;
            }
            return in_array($parentRole, ['SALES', 'MANAGER', 'DIRECTOR', 'LEADOWIEC'], true);
        }

        return false;
    }

    private function normalizeRole(?string $role): ?string
    {
        if (!$role) {
            return null;
        }

        $upper = Str::upper($role);
        if (array_key_exists($upper, self::ROLE_LEVELS)) {
            return $upper;
        }
        if ($upper === 'LEADOWIEC') {
            return 'LEADOWIEC';
        }
        return null;
    }
}
