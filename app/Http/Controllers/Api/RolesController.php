<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function index(Request $request)
    {
        $q = Role::query();
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Role $role)
    {
        return $role->load('permissions');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255|unique:roles,code',
            'name' => 'required|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);
        $role = Role::create($data);
        if (isset($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }
        return response()->json($role->load('permissions'), 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'code' => 'sometimes|required|string|max:255|unique:roles,code,'.$role->id,
            'name' => 'sometimes|required|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);
        $role->update($data);
        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids'] ?? []);
        }
        return $role->load('permissions');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->noContent();
    }
}
