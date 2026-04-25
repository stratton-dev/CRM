<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TokenContext
{
    public function __construct(
        private readonly Request $request,
        private readonly SupabaseTokenService $tokens
    ) {
    }

    public function payload(): array
    {
        $payload = $this->request->attributes->get('supabase_payload');
        return is_array($payload) ? $payload : [];
    }

    public function actorSupabaseId(): string
    {
        $payload = $this->payload();
        $subject = (string) ($payload['sub'] ?? '');
        if ($subject !== '') {
            return $subject;
        }

        $fallback = (string) ($this->request->user()?->supabase_id ?? '');
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

        $roleMap = config('supabase.role_map', []);
        $mappedRoles = array_map(fn (string $role) => $roleMap[$role] ?? $role, $roles);

        $rolePriority = config('supabase.role_priority', []);
        foreach ($rolePriority as $roleCode) {
            if (in_array($roleCode, $mappedRoles, true)) {
                return Str::upper($roleCode);
            }
        }

        return $mappedRoles ? Str::upper($mappedRoles[0]) : null;
    }

    public function teamGroupPath(): ?string
    {
        // Supabase nie uzywa grup Keycloak – fallback do danych usera
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
