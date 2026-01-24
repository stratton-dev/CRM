<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationsController extends Controller
{
    public function index(Request $request)
    {
        $q = Organization::query();
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%$search%")
                  ->orWhere('nip', 'like', "%$search%");
            });
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Organization $organization)
    {
        return $organization->loadCount(['users', 'clients']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'nullable|string|in:single,network',
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:255|unique:organizations,nip',
            'regon' => 'nullable|string|max:255',
            'krs' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'gus_synced_at' => 'nullable|date',
        ]);
        $organization = Organization::create($data);
        return response()->json($organization, 201);
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'type' => 'sometimes|in:single,network',
            'name' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:255|unique:organizations,nip,'.$organization->id,
            'regon' => 'nullable|string|max:255',
            'krs' => 'nullable|string|max:255',
            'address_json' => 'nullable|array',
            'gus_synced_at' => 'nullable|date',
        ]);
        $organization->update($data);
        return $organization;
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();
        return response()->noContent();
    }
}
