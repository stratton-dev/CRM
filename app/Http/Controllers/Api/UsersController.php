<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'role_id' => 'nullable|integer|exists:roles,id',
            'parent_id' => 'nullable|integer|exists:users,id',
            'hierarchical_id' => 'nullable|string|max:255',
            'crm_number' => 'nullable|string|max:255',
            'rank' => 'nullable|string|max:255',
            'contract_status' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'documents_json' => 'nullable|array',
            'is_removed_from_structure' => 'nullable|boolean',
            'is_blocked' => 'nullable|boolean',
            'points' => 'nullable|integer',
            'renewal_commission_rate' => 'nullable|numeric|min:0|max:1',
            'override_commission_rate' => 'nullable|numeric|min:0|max:1',
            'active' => 'nullable|boolean',
        ]);

        if (!isset($data['role_id']) && isset($data['role'])) {
            $role = $this->resolveRole($data['role']);
            $data['role_id'] = $role?->id;
        }

        unset($data['role']);

        $data['password'] = Str::random(32);
        $data['active'] = $data['active'] ?? true;

        $user = User::create($data);
        $user->load('role:id,code,name');

        return response()->json($this->formatUser($user), 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',
            'role_id' => 'nullable|integer|exists:roles,id',
            'parent_id' => 'nullable|integer|exists:users,id',
            'hierarchical_id' => 'nullable|string|max:255',
            'crm_number' => 'nullable|string|max:255',
            'rank' => 'nullable|string|max:255',
            'contract_status' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'documents_json' => 'nullable|array',
            'is_removed_from_structure' => 'nullable|boolean',
            'is_blocked' => 'nullable|boolean',
            'points' => 'nullable|integer',
            'renewal_commission_rate' => 'nullable|numeric|min:0|max:1',
            'override_commission_rate' => 'nullable|numeric|min:0|max:1',
            'active' => 'nullable|boolean',
        ]);

        if (!isset($data['role_id']) && isset($data['role'])) {
            $role = $this->resolveRole($data['role']);
            $data['role_id'] = $role?->id;
        }

        unset($data['role']);

        $user->fill($data)->save();
        $user->load('role:id,code,name');

        return $this->formatUser($user);
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
            'id' => (string) ($user->keycloak_id ?: $user->id),
            'keycloakId' => $user->keycloak_id,
            'keycloakUsername' => $user->keycloak_username,
            'organizationId' => $user->organization_id ? (string) $user->organization_id : null,
            'roleId' => $user->role_id ? (string) $user->role_id : null,
            'role' => $user->role_cached ?: $user->role?->code,
            'roleName' => $user->role?->name,
            'teamId' => $user->team_id,
            'parentId' => $user->parent_id ? (string) $user->parent_id : null,
            'parentKeycloakId' => $user->parent_keycloak_id,
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
            'points' => $user->points,
            'renewalCommissionRate' => $user->renewal_commission_rate,
            'overrideCommissionRate' => $user->override_commission_rate,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'active' => (bool) $user->active,
            'teamGroupPath' => $user->team_group_path,
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
