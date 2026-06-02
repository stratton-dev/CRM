<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\SupabaseAdminService;
use App\Services\Auth\TokenContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $current = $request->user();
        $query = User::query()->with('role:id,code,name');

        if ($current && $current->role) {
            $roleCode = $current->role->code;
            $teamId = $current->team_id;

            if (in_array($roleCode, ['DIRECTOR', 'MANAGER'], true) && !$teamId) {
                return response()->json([]);
            }

            if ($roleCode === 'DIRECTOR') {
                $query->where('team_id', $teamId)->whereIn('role_id', $this->roleIds(['DIRECTOR', 'MANAGER', 'SALES']));
            } elseif ($roleCode === 'MANAGER') {
                $query->where('team_id', $teamId)->whereIn('role_id', $this->roleIds(['MANAGER', 'SALES']));
            } elseif ($roleCode === 'SALES') {
                $query->whereKey($current->id);
            }
        }

        $users = $query->get();

        return $users->map(fn (User $user) => $this->formatUser($user));
    }

    public function show(User $user)
    {
        $user->load('role:id,code,name');
        return $this->formatUser($user);
    }

    // Tworzenie użytkownika idzie WYŁĄCZNIE przez StructureUsersController::store
    // (POST /v1/users) -> StructureService::createUser, które tworzy konto Supabase Auth
    // + rekord w `users` + hierarchię. Ta metoda była martwym kodem (apiResource users
    // ma ->only(['index','show','update','destroy']), bez 'store') i została usunięta,
    // by istniała jedna ścieżka dodawania użytkownika.

    public function update(Request $request, User $user, TokenContext $context)
    {
        $roleCode   = $context->primaryRole();
        $actorUuid  = $context->actorSupabaseId();
        $isAdmin    = in_array($roleCode, ['ADMIN', 'DIRECTOR'], true);
        $isSelf     = $actorUuid !== '' && $user->supabase_id === $actorUuid;
        $isManagerOfUser = $roleCode === 'MANAGER'
            && $request->user()?->team_id !== null
            && $user->team_id === $request->user()->team_id;

        // Only ADMIN/DIRECTOR, the user themselves, or a MANAGER of the same team may update.
        if (!$isAdmin && !$isSelf && !$isManagerOfUser) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Merge first_name/last_name → name (and vice-versa) before validation
        // so the rest of the flow can rely on a single canonical `name`.
        $this->mergeNameFields($request);

        if ($isAdmin) {
            // Admins and directors may update every field, including password.
            $data = $request->validate([
                'name'                        => 'sometimes|required|string|max:255',
                'first_name'                  => 'nullable|string|max:100',
                'last_name'                   => 'nullable|string|max:150',
                'email'                       => 'sometimes|required|email|max:255',
                'phone'                       => 'nullable|string|max:255',
                'password'                    => 'nullable|string|min:8|max:128',
                'role'                        => 'nullable|string|max:100',
                'role_id'                     => 'nullable|integer|exists:roles,id',
                'parent_id'                   => 'nullable|integer|exists:users,id',
                'hierarchical_id'             => 'nullable|string|max:255',
                'crm_number'                  => 'nullable|string|max:255',
                'rank'                        => 'nullable|string|max:255',
                'contract_status'             => 'nullable|string|max:255',
                'type'                        => 'nullable|string|max:255',
                'address_json'                => 'nullable|array',
                'documents_json'              => 'nullable|array',
                'is_removed_from_structure'   => 'nullable|boolean',
                'is_blocked'                  => 'nullable|boolean',
                'is_agent_authorized'         => 'nullable|boolean',
                'points'                      => 'nullable|integer',
                'renewal_commission_rate'     => 'nullable|numeric|min:0|max:1',
                'override_commission_rate'    => 'nullable|numeric|min:0|max:1',
                'active'                      => 'nullable|boolean',
                'leadowiec_opiekun_id'        => 'nullable|exists:users,id',
                'leadowiec_commission_rate'   => 'nullable|numeric|min:0|max:1',
            ]);

            // Hasło: ustawiamy w Supabase Auth (źródło logowania) ORAZ zapisujemy
            // jawnie w plain_password (do podglądu/edycji w panelu). Kolumna `password`
            // (hashed) nie jest używana do logowania.
            $newPassword = $data['password'] ?? null;
            unset($data['password']);
            if (!empty($newPassword)) {
                $data['plain_password'] = $newPassword;
            }

            if (!isset($data['role_id']) && isset($data['role'])) {
                $role = $this->resolveRole($data['role']);
                $data['role_id']     = $role?->id;
                $data['role_cached'] = $role?->code;
            }

            if (!isset($data['role_cached']) && isset($data['role_id'])) {
                $role = Role::query()->find($data['role_id']);
                $data['role_cached'] = $role?->code;
            }

            unset($data['role']);

            // Validate opiekun role when assigning leadowiec_opiekun_id
            if (array_key_exists('leadowiec_opiekun_id', $data) && $data['leadowiec_opiekun_id']) {
                $opiekun = User::find($data['leadowiec_opiekun_id']);
                $allowedRoles = ['SALES', 'MANAGER', 'DIRECTOR', 'ADMIN'];
                if (!$opiekun || !in_array($opiekun->role_cached, $allowedRoles, true)) {
                    return response()->json(['message' => 'Opiekun musi być handlowcem lub menedżerem.'], 422);
                }
            }
        } else {
            // Non-admins may only update basic profile fields — no role, status, or financial fields.
            $data = $request->validate([
                'name'         => 'sometimes|required|string|max:255',
                'first_name'   => 'nullable|string|max:100',
                'last_name'    => 'nullable|string|max:150',
                'phone'        => 'nullable|string|max:255',
                'address_json' => 'nullable|array',
                'rank'         => 'nullable|string|max:255',
            ]);
        }

        $user->fill($data)->save();
        $user->load('role:id,code,name');

        // Sync role/email/phone/password to Supabase Auth so login + role
        // enforcement stay consistent. Failures here are not fatal — DB is
        // source of truth except for password (Supabase-only).
        if ($isAdmin && $user->supabase_id) {
            $admin = app(SupabaseAdminService::class);
            if ($admin->isConfigured()) {
                $patch = [];
                if (array_key_exists('email', $data)) $patch['email'] = $data['email'];
                if (array_key_exists('phone', $data)) $patch['phone'] = $data['phone'];
                if (isset($data['role_cached'])) $patch['role'] = $data['role_cached'];
                if (array_key_exists('name', $data)) $patch['name'] = $data['name'];
                if (array_key_exists('is_blocked', $data)) {
                    $patch['ban_duration'] = $data['is_blocked'] ? '876000h' : 'none';
                }
                if (!empty($newPassword)) {
                    $patch['password'] = $newPassword;
                }
                if (!empty($patch)) {
                    try {
                        $admin->updateUser($user->supabase_id, $patch);
                    } catch (\Throwable $e) {
                        Log::warning('Supabase updateUser sync failed', [
                            'user' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return $this->formatUser($user);
    }

    public function destroy(User $user, TokenContext $context, SupabaseAdminService $admin)
    {
        $actorRole = $context->primaryRole();
        if ($actorRole !== 'ADMIN') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if ($context->actorSupabaseId() !== '' && $user->supabase_id === $context->actorSupabaseId()) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }

        $supabaseDeleted = false;
        $supabaseError = null;

        if ($user->supabase_id && $admin->isConfigured()) {
            try {
                $admin->deleteUser($user->supabase_id);
                $supabaseDeleted = true;
            } catch (\Throwable $e) {
                $supabaseError = $e->getMessage();
                Log::warning('Supabase deleteUser failed; DB delete proceeds', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Detach children so we don't leave dangling parent_supabase_id pointers.
        if ($user->supabase_id) {
            User::query()
                ->where('parent_supabase_id', $user->supabase_id)
                ->update(['parent_supabase_id' => null]);
        }
        User::query()->where('parent_id', $user->id)->update(['parent_id' => null]);

        $user->delete();

        return response()->json([
            'deleted' => true,
            'supabaseDeleted' => $supabaseDeleted,
            'supabaseError' => $supabaseError,
        ]);
    }

    public function sendPasswordReset(User $user, TokenContext $context, SupabaseAdminService $admin)
    {
        $actorRole = $context->primaryRole();
        $isAdmin = in_array($actorRole, ['ADMIN', 'DIRECTOR'], true);
        $isSelf = $context->actorSupabaseId() !== '' && $user->supabase_id === $context->actorSupabaseId();
        if (!$isAdmin && !$isSelf) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if (!$user->email) {
            return response()->json(['message' => 'User has no email.'], 422);
        }
        if (!$admin->isConfigured()) {
            return response()->json(['message' => 'Supabase admin not configured.'], 503);
        }

        $result = $admin->generatePasswordResetLink($user->email);
        return response()->json([
            'sent' => true,
            'email' => $user->email,
            'actionLink' => $result['action_link'],
        ]);
    }

    /**
     * Keep `name`, `first_name`, `last_name` in sync on incoming requests so
     * controllers can rely on whichever the caller sent.
     */
    private function mergeNameFields(Request $request): void
    {
        $first = trim((string) $request->input('first_name'));
        $last  = trim((string) $request->input('last_name'));
        $name  = trim((string) $request->input('name'));

        if (($first !== '' || $last !== '') && $name === '') {
            $request->merge(['name' => trim($first . ' ' . $last)]);
        }

        if ($name !== '' && $first === '' && $last === '') {
            $parts = preg_split('/\s+/u', $name) ?: [];
            $f = array_shift($parts) ?? '';
            $l = trim(implode(' ', $parts));
            $request->merge([
                'first_name' => $f !== '' ? $f : null,
                'last_name'  => $l !== '' ? $l : null,
            ]);
        }
    }

    private function resolveRole(string $roleCode): ?Role
    {
        $normalized = Str::upper($roleCode);

        return Role::firstOrCreate(
            ['code' => $normalized],
            ['name' => Str::title(str_replace(['_', '-'], ' ', $normalized))]
        );
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => (string) ($user->supabase_id ?: $user->id),
            'supabaseId' => $user->supabase_id,
            'organizationId' => $user->organization_id ? (string) $user->organization_id : null,
            'roleId' => $user->role_id ? (string) $user->role_id : null,
            'role' => $user->role_cached ?: $user->role?->code,
            'roleName' => $user->role?->name,
            'teamId' => $user->team_id,
            'parentId' => $user->parent_id ? (string) $user->parent_id : null,
            'parentSupabaseId' => $user->parent_supabase_id,
            'hierarchicalId' => $user->hierarchical_id,
            'hierarchicalCode' => $user->hierarchical_code,
            'crmNumber' => $user->crm_number,
            'rank' => $user->rank,
            'contractStatus' => $user->contract_status,
            'type' => $user->type,
            'addressData' => $user->address_json,
            'documents' => $user->documents_json,
            'isRemovedFromStructure' => (bool) $user->is_removed_from_structure,
            'isBlocked' => (bool) $user->is_blocked,
            'isAgentAuthorized' => (bool) $user->is_agent_authorized,
            'points' => $user->points,
            'renewalCommissionRate' => $user->renewal_commission_rate,
            'overrideCommissionRate' => $user->override_commission_rate,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'active' => (bool) $user->active,
            'teamGroupPath' => $user->team_group_path,
            'leadowiecOpiekunId' => $user->leadowiec_opiekun_id,
            'leadowiecCommissionRate' => $user->leadowiec_commission_rate,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'plainPassword' => $user->plain_password,
        ];
    }

    private function roleIds(array $codes): array
    {
        return Role::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();
    }
}
