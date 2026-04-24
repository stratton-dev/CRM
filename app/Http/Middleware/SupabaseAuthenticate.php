<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SupabaseTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseAuthenticate
{
    public function __construct(private readonly SupabaseTokenService $tokens)
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

        // Przechowaj payload do uzycia przez TokenContext
        $request->attributes->set('supabase_payload', $payload);
        $user = $this->resolveUser($payload);
        Auth::setUser($user);

        return $next($request);
    }

    private function resolveUser(array $payload): User
    {
        $supabaseId = (string) ($payload['sub'] ?? '');
        $email      = (string) ($payload['email'] ?? '');

        if (!$supabaseId && !$email) {
            throw new RuntimeException('Token does not contain user identity.');
        }

        $user = User::query()
            ->when($supabaseId, fn ($q) => $q->where('keycloak_id', $supabaseId))
            ->when(!$supabaseId && $email, fn ($q) => $q->where('email', $email))
            ->first();

        if (!$user && $email) {
            // Sprobuj znalezc po emailu gdy nie znaleziono po ID
            $user = User::query()->where('email', $email)->first();
        }

        $name = (string) ($payload['user_metadata']['full_name']
            ?? $payload['user_metadata']['name']
            ?? $email);

        if (!$user) {
            $user = User::create([
                'keycloak_id'      => $supabaseId ?: null,
                'keycloak_username' => $email ?: null,
                'name'             => $name ?: 'Supabase User',
                'email'            => $email ?: Str::uuid()->toString() . '@local',
                'password'         => Str::random(32),
                'active'           => true,
            ]);
        } else {
            $user->fill([
                'keycloak_id'      => $supabaseId ?: $user->keycloak_id,
                'keycloak_username' => $email ?: $user->keycloak_username,
                'name'             => $name ?: $user->name,
                'active'           => true,
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

        $roleMap = config('supabase.role_map', []);
        $mappedRoles = array_map(fn (string $r) => $roleMap[$r] ?? $r, $roles);

        $rolePriority = config('supabase.role_priority', []);
        $selected = null;

        foreach ($rolePriority as $roleCode) {
            if (in_array($roleCode, $mappedRoles, true)) {
                $selected = $roleCode;
                break;
            }
        }

        $selected ??= $mappedRoles[0] ?? null;
        if (!$selected) {
            return null;
        }

        $role = Role::firstOrCreate(['code' => $selected], ['description' => $selected]);

        if ($user->role_id !== $role->id) {
            $user->role()->associate($role)->save();
        }

        return $role->code;
    }
}
