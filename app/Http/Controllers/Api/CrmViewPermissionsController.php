<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmViewPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmViewPermissionsController extends Controller
{
    public function index()
    {
        return CrmViewPermission::query()
            ->orderBy('view_key')
            ->get(['view_key', 'roles']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.view_key' => 'required|string|max:255',
            'permissions.*.roles' => 'array',
            'permissions.*.roles.*' => 'string|max:255',
        ]);

        $permissions = collect($data['permissions'] ?? []);
        $viewKeys = $permissions->pluck('view_key')->unique()->values();

        DB::transaction(function () use ($permissions, $viewKeys) {
            CrmViewPermission::query()
                ->whereNotIn('view_key', $viewKeys)
                ->delete();

            foreach ($permissions as $item) {
                $roles = array_values(array_unique($item['roles'] ?? []));
                CrmViewPermission::updateOrCreate(
                    ['view_key' => $item['view_key']],
                    ['roles' => $roles]
                );
            }
        });

        return CrmViewPermission::query()
            ->orderBy('view_key')
            ->get(['view_key', 'roles']);
    }
}
