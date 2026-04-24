<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmBroadcast;
use Illuminate\Http\Request;

class CrmBroadcastsController extends Controller
{
    public function index()
    {
        return CrmBroadcast::query()
            ->with('targets')
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'event_key' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'enabled' => 'nullable|boolean',
            'targets' => 'nullable|array',
            'targets.*.target_type' => 'required_with:targets|in:ROLE,TEAM',
            'targets.*.target_value' => 'required_with:targets|string|max:255',
        ]);

        $broadcast = CrmBroadcast::create($data);
        if (!empty($data['targets'])) {
            $broadcast->targets()->createMany($data['targets']);
        }

        return $broadcast->load('targets');
    }

    public function update(Request $request, CrmBroadcast $crmBroadcast)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'event_key' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:255',
            'enabled' => 'nullable|boolean',
            'targets' => 'nullable|array',
            'targets.*.target_type' => 'required_with:targets|in:ROLE,TEAM',
            'targets.*.target_value' => 'required_with:targets|string|max:255',
        ]);

        $crmBroadcast->update($data);
        if (array_key_exists('targets', $data)) {
            $crmBroadcast->targets()->delete();
            if (!empty($data['targets'])) {
                $crmBroadcast->targets()->createMany($data['targets']);
            }
        }

        return $crmBroadcast->load('targets');
    }

    public function destroy(CrmBroadcast $crmBroadcast)
    {
        $crmBroadcast->delete();
        return response()->noContent();
    }
}
