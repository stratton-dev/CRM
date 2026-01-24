<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\KeycloakTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakAuthenticate
{
    public function __construct(private readonly KeycloakTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        try {
            $payload = $this->tokens->decode($token);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        $request->attributes->set('keycloak_payload', $payload);
        $user = $this->resolveUser($payload);
        Auth::setUser($user);

        return $next($request);
    }

    private function resolveUser(array $payload): User
    {
        $email = (string) ($payload['email'] ?? '');
        $keycloakId = (string) ($payload['sub'] ?? '');
        $username = (string) ($payload['preferred_username'] ?? '');
        $name = (string) ($payload['name'] ?? $username ?: $email);
        $teamId = $this->extractTeamId($payload);
        $teamGroupPath = $this->extractTeamGroupPath($payload);

        if (!$keycloakId && !$email) {
            throw new RuntimeException('Token does not contain user identity.');
        }

        $user = User::query()
            ->when($keycloakId, fn ($q) => $q->where('keycloak_id', $keycloakId))
            ->when($email, function ($q) use ($keycloakId, $email) {
                if ($keycloakId) {
                    $q->orWhere('email', $email);
                } else {
                    $q->where('email', $email);
                }
            })
            ->first();

        if (!$user) {
            $user = User::create([
                'keycloak_id' => $keycloakId ?: null,
                'keycloak_username' => $username ?: null,
                'team_id' => $teamId,
                'team_group_path' => $teamGroupPath,
                'name' => $name ?: 'Keycloak User',
                'email' => $email ?: Str::uuid()->toString().'@local',
                'password' => Str::random(32),
                'active' => true,
            ]);
        } else {
            $user->fill([
                'keycloak_id' => $keycloakId ?: $user->keycloak_id,
                'keycloak_username' => $username ?: $user->keycloak_username,
                'team_id' => $teamId ?: $user->team_id,
                'team_group_path' => $teamGroupPath ?: $user->team_group_path,
                'name' => $name ?: $user->name,
                'email' => $email ?: $user->email,
                'active' => true,
            ])->save();
        }

        $selectedRole = $this->syncRole($user, $this->tokens->extractRoles($payload));
        if ($selectedRole) {
            $user->fill(['role_cached' => $selectedRole])->save();
        }

        return $user->refresh();
    }

    private function syncRole(User $user, array $roles): ?string
    {
        if (!$roles) {
            return null;
        }

        $roleMap = config('keycloak.role_map', []);
        $mappedRoles = array_map(function (string $role) use ($roleMap): string {
            return $roleMap[$role] ?? $role;
        }, $roles);

        $rolePriority = config('keycloak.role_priority', []);
        $selected = null;

        foreach ($rolePriority as $roleCode) {
            if (in_array($roleCode, $mappedRoles, true)) {
                $selected = $roleCode;
                break;
            }
        }

        $selected ??= $mappedRoles[0];

        $role = Role::firstOrCreate(
            ['code' => $selected],
            ['name' => Str::title(str_replace(['_', '-'], ' ', $selected))]
        );

        $permissionMap = config('keycloak.role_permissions', []);
        if (isset($permissionMap[$role->code])) {
            $permissionCodes = $permissionMap[$role->code];
            foreach ($permissionCodes as $code) {
                Permission::firstOrCreate(
                    ['code' => $code],
                    ['description' => $code]
                );
            }
            $permissionIds = Permission::query()
                ->whereIn('code', $permissionCodes)
                ->pluck('id')
                ->all();
            $role->permissions()->sync($permissionIds);
        }

        if ($user->role_id !== $role->id) {
            $user->role()->associate($role)->save();
        }

        return $role->code;
    }

    private function extractTeamId(array $payload): ?string
    {
        $groups = $payload['groups'] ?? [];
        if (!is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (!is_string($group)) {
                continue;
            }
            if (str_starts_with($group, '/teams/')) {
                $parts = explode('/', trim($group, '/'));
                return $parts[count($parts) - 1] ?: null;
            }
        }

        return null;
    }

    private function extractTeamGroupPath(array $payload): ?string
    {
        $groups = $payload['groups'] ?? [];
        if (!is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (!is_string($group)) {
                continue;
            }
            if (str_starts_with($group, '/teams/')) {
                return $group;
            }
        }

        return null;
    }
}
