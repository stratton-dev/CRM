<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionsController extends Controller
{
    public function index(Request $request)
    {
        $q = Permission::query();
        return $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
    }

    public function show(Permission $permission)
    {
        return $permission;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255|unique:permissions,code',
            'description' => 'required|string|max:255',
        ]);
        $permission = Permission::create($data);
        return response()->json($permission, 201);
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'code' => 'sometimes|required|string|max:255|unique:permissions,code,'.$permission->id,
            'description' => 'sometimes|required|string|max:255',
        ]);
        $permission->update($data);
        return $permission;
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return response()->noContent();
    }
}
