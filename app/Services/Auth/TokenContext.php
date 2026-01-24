<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TokenContext
{
    public function __construct(
        private readonly Request $request,
        private readonly KeycloakTokenService $tokens
    ) {
    }

    public function payload(): array
    {
        $payload = $this->request->attributes->get('keycloak_payload');
        return is_array($payload) ? $payload : [];
    }

    public function actorKeycloakId(): string
    {
        $payload = $this->payload();
        $subject = (string) ($payload['sub'] ?? '');
        if ($subject !== '') {
            return $subject;
        }

        $fallback = (string) ($this->request->user()?->keycloak_id ?? '');
        if ($fallback !== '') {
            return $fallback;
        }

        $email = (string) ($payload['email'] ?? $this->request->user()?->email ?? '');
        if ($email !== '') {
            return $email;
        }

        return (string) ($this->request->user()?->id ?? '');
    }

    public function roles(): array
    {
        $payload = $this->payload();
        if ($payload) {
            return $this->tokens->extractRoles($payload);
        }

        $fallback = $this->request->user()?->role_cached;
        return $fallback ? [$fallback] : [];
    }

    public function primaryRole(): ?string
    {
        $roles = $this->roles();
        if (!$roles) {
            $user = $this->request->user();
            return $user?->role_cached ?: $user?->role?->code;
        }

        $roleMap = config('keycloak.role_map', []);
        $mappedRoles = array_map(function (string $role) use ($roleMap): string {
            return $roleMap[$role] ?? $role;
        }, $roles);

        $rolePriority = config('keycloak.role_priority', []);
        foreach ($rolePriority as $roleCode) {
            if (in_array($roleCode, $mappedRoles, true)) {
                return Str::upper($roleCode);
            }
        }

        return $mappedRoles ? Str::upper($mappedRoles[0]) : null;
    }

    public function teamGroupPath(): ?string
    {
        $payload = $this->payload();
        $groups = $payload['groups'] ?? [];
        if (is_array($groups)) {
            foreach ($groups as $group) {
                if (!is_string($group)) {
                    continue;
                }
                if (str_starts_with($group, '/teams/')) {
                    return $group;
                }
            }
        }

        return $this->request->user()?->team_group_path;
    }

    public function teamCode(): ?string
    {
        $path = $this->teamGroupPath();
        if (!$path) {
            return null;
        }

        $parts = array_values(array_filter(explode('/', $path)));
        return $parts ? Arr::last($parts) : null;
    }
}
