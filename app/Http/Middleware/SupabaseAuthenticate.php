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
        // Pass OPTIONS preflight requests through — CORS middleware handles them
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        try {
            $payload = $this->tokens->decode($token);
        } catch (RuntimeException $exception) {
            // 503 = auth infrastructure down (e.g. JWKS unreachable) → client retries
            // and keeps the session. 401 = genuinely bad/expired token.
            $status = $exception->getCode() === 503 ? 503 : 401;
            return response()->json(['message' => $exception->getMessage()], $status);
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
            ->when($supabaseId, fn ($q) => $q->where('supabase_id', $supabaseId))
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
                'supabase_id'      => $supabaseId ?: null,
                'name'             => $name ?: 'Supabase User',
                'email'            => $email ?: Str::uuid()->toString() . '@local',
                'password'         => Str::random(32),
                'active'           => true,
            ]);
        } else {
            $user->fill([
                'supabase_id'      => $supabaseId ?: $user->supabase_id,
                'name'             => $name ?: $user->name,
                'active'           => true,
            ])->save();
        }

        // Rola: token Supabase (app_metadata.role) służy WYŁĄCZNIE do *inicjalizacji*
        // roli przy pierwszym logowaniu konta, które jeszcze nie ma roli w bazie.
        // Gdy użytkownik ma już rolę w DB, baza jest jedynym źródłem prawdy — zmiany
        // z panelu (Użytkownicy/Struktura) zapisują się do DB i NIE wolno ich cofać
        // rolą zapieczoną w już wydanym (potencjalnie nieaktualnym) JWT. Inaczej
        // zmiana roli „nie zapisuje się" — middleware nadpisuje ją starą wartością
        // z tokenu przy następnym requeście tego użytkownika.
        $hasDbRole = $user->role_id !== null || !empty($user->role_cached);
        if (!$hasDbRole) {
            $selectedRole = $this->syncRole($user, $this->tokens->extractRoles($payload));
            if ($selectedRole && $user->role_cached !== $selectedRole) {
                $user->fill(['role_cached' => $selectedRole])->save();
            }
        } elseif ($user->role_id === null && !empty($user->role_cached)) {
            // Self-heal: userzy tworzeni przez Strukturę mają role_cached, ale
            // role_id=NULL (init-only syncRole wyżej jest pomijany, bo role_cached
            // jest już ustawione). NULL role_id psuje scoping listy userów
            // (UsersController::index bez $current->role zwraca WSZYSTKICH) oraz
            // granty permission_role (Gate::define zwraca false gdy !$user->role).
            // Uzupełniamy role_id z DB (role_cached) — NIE z JWT, więc nie cofa roli.
            $roleId = \Illuminate\Support\Facades\Cache::remember(
                "supabase_role_id:{$user->role_cached}",
                3600,
                fn () => Role::firstOrCreate(['code' => $user->role_cached], ['name' => $user->role_cached])->id
            );
            if ($roleId) {
                $user->role_id = $roleId;
                $user->save();
            }
        }

        // Bez $user->refresh() — to był zbędny pełny SELECT na KAŻDYM requeście.
        // Atrybuty są już aktualne w pamięci (ustawione powyżej). Przy połączeniu
        // cross-region do Supabase każdy round-trip kosztuje ~150ms, więc to liczyło się.
        return $user;
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

        // Cache id roli per kod (file cache = lokalny, ~0ms) zamiast SELECT/firstOrCreate
        // do Supabase na KAŻDYM authenticated requeście. Role zmieniają się rzadko.
        $roleId = \Illuminate\Support\Facades\Cache::remember(
            "supabase_role_id:{$selected}",
            3600,
            fn () => Role::firstOrCreate(['code' => $selected], ['name' => $selected])->id
        );

        if ($user->role_id !== $roleId) {
            $user->role_id = $roleId;
            $user->save();
        }

        return $selected;
    }
}
